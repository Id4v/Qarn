<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class TeamManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipRepository $memberships,
        private InvitationRepository $invitations,
    ) {
    }

    public function createTeam(User $creator, string $name): Team
    {
        $this->assertVerified($creator);
        $team = new Team($name);
        $this->entityManager->persist($team);
        $this->entityManager->persist(new Membership($team, $creator, MembershipRole::OWNER));
        $this->entityManager->flush();

        return $team;
    }

    /** @return array{invitation: Invitation, token: string} */
    public function invite(User $actor, Team $team, string $email): array
    {
        $email = strtolower(trim($email));

        return $this->entityManager->wrapInTransaction(function () use ($actor, $team, $email): array {
            $this->lockTeam($team);
            $this->assertOwner($actor, $team);
            if (null !== $this->invitations->findPendingForEmail($team, $email)) {
                throw new \DomainException('Une invitation active existe déjà pour cette adresse.');
            }

            $invitedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($invitedUser && $this->memberships->findFor($team, $invitedUser)) {
                throw new \DomainException('Cette personne est déjà membre de l’équipe.');
            }

            $token = bin2hex(random_bytes(32));
            $createdAt = new \DateTimeImmutable();
            $invitation = new Invitation(
                $team,
                $actor,
                $email,
                hash('sha256', $token),
                $createdAt->modify('+14 days'),
                $createdAt,
            );
            $this->entityManager->persist($invitation);

            return ['invitation' => $invitation, 'token' => $token];
        });
    }

    public function revokeInvitation(User $actor, Team $team, Invitation $invitation): void
    {
        $this->entityManager->wrapInTransaction(function () use ($actor, $team, $invitation): void {
            $this->lockTeam($team);
            $this->assertOwner($actor, $team);
            $locked = $this->entityManager->find(Invitation::class, $invitation->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$locked instanceof Invitation || $locked->getTeam() !== $team || !$locked->isPending()) {
                throw new \DomainException('Cette invitation n’est plus révocable.');
            }

            $locked->revoke();
        });
    }

    public function acceptInvitation(Invitation $invitation, string $token, User $user): void
    {
        $this->assertVerified($user);

        $this->entityManager->wrapInTransaction(function () use ($invitation, $token, $user): void {
            $this->lockTeam($invitation->getTeam());
            $locked = $this->entityManager->find(Invitation::class, $invitation->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$locked instanceof Invitation
                || !$locked->isPending()
                || !hash_equals($locked->getTokenHash(), hash('sha256', $token))
                || strtolower((string) $user->getEmail()) !== $locked->getEmail()) {
                throw new \DomainException('Cette invitation est invalide, expirée ou déjà utilisée.');
            }
            if (null !== $this->memberships->findFor($locked->getTeam(), $user)) {
                throw new \DomainException('Ce compte est déjà membre de l’équipe.');
            }

            $locked->accept();
            $this->entityManager->persist(new Membership($locked->getTeam(), $user, MembershipRole::MEMBER));
        });
    }

    public function promote(User $actor, Team $team, Membership $membership): void
    {
        $this->assertOwner($actor, $team);
        $this->assertMembershipInTeam($team, $membership);
        $membership->setRole(MembershipRole::OWNER);
        $this->entityManager->flush();
    }

    public function demote(User $actor, Team $team, Membership $membership): void
    {
        $this->entityManager->wrapInTransaction(function () use ($actor, $team, $membership): void {
            $this->lockTeam($team);
            $this->assertOwner($actor, $team);
            $this->assertMembershipInTeam($team, $membership);
            if (MembershipRole::OWNER === $membership->getRole() && $this->memberships->countOwners($team) <= 1) {
                throw new \DomainException('Une équipe doit toujours conserver au moins un propriétaire.');
            }

            $membership->setRole(MembershipRole::MEMBER);
        });
    }

    public function removeMember(User $actor, Team $team, Membership $membership): void
    {
        $this->entityManager->wrapInTransaction(function () use ($actor, $team, $membership): void {
            $this->lockTeam($team);
            $this->assertOwner($actor, $team);
            $this->assertMembershipInTeam($team, $membership);
            $this->removeMembershipSafely($team, $membership);
        });
    }

    public function leave(User $user, Team $team): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $team): void {
            $this->lockTeam($team);
            $membership = $this->memberships->findFor($team, $user);
            if (!$membership) {
                throw new AccessDeniedException();
            }

            $this->removeMembershipSafely($team, $membership);
        });
    }

    public function deleteTeamAsLastOwner(User $actor, Team $team, string $confirmation): void
    {
        $this->entityManager->wrapInTransaction(function () use ($actor, $team, $confirmation): void {
            $this->lockTeam($team);
            $this->assertOwner($actor, $team);
            if ($team->getName() !== trim($confirmation) || 1 !== $this->memberships->countOwners($team)) {
                throw new \DomainException('Pour supprimer l’équipe, son dernier propriétaire doit confirmer son nom.');
            }

            $this->entityManager->remove($team);
        });
    }

    private function removeMembershipSafely(Team $team, Membership $membership): void
    {
        if (MembershipRole::OWNER === $membership->getRole() && $this->memberships->countOwners($team) <= 1) {
            throw new \DomainException('Transférez la propriété ou supprimez définitivement l’équipe avant de partir.');
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    private function lockTeam(Team $team): void
    {
        if (null === $team->getId() || !$this->entityManager->find(Team::class, $team->getId(), LockMode::PESSIMISTIC_WRITE)) {
            throw new \DomainException('Cette équipe n’existe plus.');
        }
    }

    private function assertOwner(User $actor, Team $team): void
    {
        $membership = $this->memberships->findFor($team, $actor);
        if (!$membership || MembershipRole::OWNER !== $membership->getRole()) {
            throw new AccessDeniedException();
        }
    }

    private function assertMembershipInTeam(Team $team, Membership $membership): void
    {
        if (null === $team->getId() || $membership->getTeam()->getId() !== $team->getId()) {
            throw new AccessDeniedException();
        }
    }

    private function assertVerified(User $user): void
    {
        if (!$user->isVerified()) {
            throw new AccessDeniedException('Vérifiez votre adresse e-mail pour continuer.');
        }
    }
}
