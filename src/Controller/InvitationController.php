<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\InvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InvitationController extends AbstractController
{
    #[Route('/invitations/accept/{id}', name: 'app_invitation_accept', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(
        int $id,
        Request $request,
        InvitationService $invitationService,
    ): Response {
        $user = $this->currentUser();
        try {
            $invitation = $invitationService->getSignedInvitation($id, $request, $user);
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $form = $this->createFormBuilder()
            ->add('accept', SubmitType::class, ['label' => 'Rejoindre l’équipe'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $invitation = $invitationService->accept($id, $request, $user);
                $this->addFlash('success', sprintf('Vous avez rejoint l’équipe %s comme membre.', $invitation->getTeam()->getName()));

                return $this->redirectToRoute('app_teams');
            } catch (\DomainException $exception) {
                throw $this->createNotFoundException($exception->getMessage());
            }
        }

        return $this->render('team/accept_invitation.html.twig', ['invitation' => $invitation, 'acceptForm' => $form]);
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
