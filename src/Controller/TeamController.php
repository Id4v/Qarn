<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Team;
use App\Entity\User;
use App\Form\InvitationFormType;
use App\Form\TeamFormType;
use App\Repository\MembershipRepository;
use App\Repository\TeamRepository;
use App\Service\InvitationService;
use App\Service\TeamManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams')]
final class TeamController extends AbstractController
{
    #[Route('', name: 'app_teams', methods: ['GET', 'POST'])]
    public function index(Request $request, TeamRepository $teams, TeamManager $teamManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(TeamFormType::class, new Team(''));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $teamManager->createTeam($user, (string) $form->get('name')->getData());
                $this->addFlash('success', 'L’équipe a été créée.');

                return $this->redirectToRoute('app_teams');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('team/index.html.twig', [
            'teams' => $teams->findForUser($user),
            'teamForm' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_team_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[IsGranted('TEAM_VIEW', subject: 'team')]
    public function show(Team $team): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('team/show.html.twig', [
            'team' => $team,
            'currentUser' => $user,
            'invitationForm' => $this->createForm(InvitationFormType::class)->createView(),
        ]);
    }

    #[Route('/{id}/invitations', name: 'app_team_invite', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_MANAGE', subject: 'team')]
    public function invite(
        Team $team,
        Request $request,
        InvitationService $invitationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(InvitationFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $invitationService->invite($user, $team, (string) $form->get('email')->getData());
                $this->addFlash('success', 'Invitation envoyée. Elle expire dans 14 jours.');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/invitations/{invitationId}/revoke', name: 'app_team_invitation_revoke', requirements: ['id' => '\\d+', 'invitationId' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_MANAGE', subject: 'team')]
    public function revokeInvitation(Team $team, int $invitationId, Request $request, EntityManagerInterface $entityManager, TeamManager $teamManager): Response
    {
        if (!$this->isCsrfTokenValid('revoke-invitation-'.$invitationId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invitation = $entityManager->getRepository(Invitation::class)->find($invitationId);
        if (!$invitation instanceof Invitation) {
            throw $this->createNotFoundException();
        }
        try {
            $teamManager->revokeInvitation($this->currentUser(), $team, $invitation);
            $this->addFlash('success', 'Invitation révoquée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/members/{membershipId}/promote', name: 'app_team_member_promote', requirements: ['id' => '\\d+', 'membershipId' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_MANAGE', subject: 'team')]
    public function promote(Team $team, int $membershipId, Request $request, MembershipRepository $memberships, TeamManager $teamManager): Response
    {
        return $this->changeMembershipRole($team, $membershipId, $request, $memberships, $teamManager, true);
    }

    #[Route('/{id}/members/{membershipId}/demote', name: 'app_team_member_demote', requirements: ['id' => '\\d+', 'membershipId' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_MANAGE', subject: 'team')]
    public function demote(Team $team, int $membershipId, Request $request, MembershipRepository $memberships, TeamManager $teamManager): Response
    {
        return $this->changeMembershipRole($team, $membershipId, $request, $memberships, $teamManager, false);
    }

    #[Route('/{id}/members/{membershipId}/remove', name: 'app_team_member_remove', requirements: ['id' => '\\d+', 'membershipId' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_MANAGE', subject: 'team')]
    public function removeMember(Team $team, int $membershipId, Request $request, MembershipRepository $memberships, TeamManager $teamManager): Response
    {
        if (!$this->isCsrfTokenValid('remove-member-'.$membershipId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $membership = $memberships->find($membershipId);
        if (!$membership instanceof Membership) {
            throw $this->createNotFoundException();
        }
        try {
            $teamManager->removeMember($this->currentUser(), $team, $membership);
            $this->addFlash('success', 'Membre retiré de l’équipe.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    #[Route('/{id}/leave', name: 'app_team_leave', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_VIEW', subject: 'team')]
    public function leave(Team $team, Request $request, TeamManager $teamManager): Response
    {
        if (!$this->isCsrfTokenValid('leave-team-'.$team->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $teamManager->leave($this->currentUser(), $team);
            $this->addFlash('success', 'Vous avez quitté l’équipe.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_teams');
    }

    #[Route('/{id}/delete', name: 'app_team_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('TEAM_DELETE', subject: 'team')]
    public function delete(Team $team, Request $request, TeamManager $teamManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-team-'.$team->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $teamManager->deleteTeamAsLastOwner($this->currentUser(), $team, $request->request->getString('confirmation'));
            $this->addFlash('success', 'L’équipe et les données qui lui sont rattachées ont été supprimées.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        return $this->redirectToRoute('app_teams');
    }

    private function changeMembershipRole(Team $team, int $membershipId, Request $request, MembershipRepository $memberships, TeamManager $teamManager, bool $promote): Response
    {
        if (!$this->isCsrfTokenValid(($promote ? 'promote-member-' : 'demote-member-').$membershipId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $membership = $memberships->find($membershipId);
        if (!$membership instanceof Membership) {
            throw $this->createNotFoundException();
        }
        try {
            if ($promote) {
                $teamManager->promote($this->currentUser(), $team, $membership);
            } else {
                $teamManager->demote($this->currentUser(), $team, $membership);
            }
            $this->addFlash('success', $promote ? 'Membre promu propriétaire.' : 'Propriétaire rétrogradé membre.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
