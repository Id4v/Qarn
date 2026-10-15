<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\InvitationRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InvitationService
{
    public function __construct(
        private TeamManager $teamManager,
        private InvitationLinkService $links,
        private InvitationRepository $invitations,
        private UriSigner $uriSigner,
        private MailerInterface $mailer,
    ) {
    }

    public function invite(User $actor, Team $team, string $email): Invitation
    {
        $result = $this->teamManager->invite($actor, $team, $email);
        $invitation = $result['invitation'];
        $signedUrl = $this->links->create($invitation, $result['token']);

        $message = new TemplatedEmail()
            ->from(new Address('no-reply@qarn.local', 'Qarn'))
            ->to($invitation->getEmail())
            ->subject(sprintf('Invitation à rejoindre %s sur Qarn', $team->getName()))
            ->htmlTemplate('team/invitation_email.html.twig')
            ->context(['team' => $team, 'invitedBy' => $actor, 'signedUrl' => $signedUrl]);
        $this->mailer->send($message);

        return $invitation;
    }

    public function getSignedInvitation(int $id, Request $request, User $user): Invitation
    {
        $invitation = $this->invitations->find($id);
        $token = $request->query->getString('token');
        $email = strtolower($request->query->getString('email'));
        if (!$invitation instanceof Invitation
            || !$this->uriSigner->checkRequest($request)
            || !$invitation->isPending()
            || !hash_equals($invitation->getTokenHash(), hash('sha256', $token))
            || $invitation->getEmail() !== $email) {
            throw new \DomainException('Ce lien d’invitation est invalide, expiré ou déjà utilisé.');
        }

        if (!$user->isVerified()) {
            throw new AccessDeniedException('Vérifiez votre adresse e-mail avant de rejoindre une équipe.');
        }
        if (strtolower((string) $user->getEmail()) !== $invitation->getEmail()) {
            throw new AccessDeniedException('Connectez-vous au compte correspondant à l’adresse invitée.');
        }

        return $invitation;
    }

    public function accept(int $id, Request $request, User $user): Invitation
    {
        $invitation = $this->getSignedInvitation($id, $request, $user);
        $this->teamManager->acceptInvitation($invitation, $request->query->getString('token'), $user);

        return $invitation;
    }
}
