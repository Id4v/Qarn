<?php

namespace App\Controller;

use App\Dto\RegistrationData;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        RegistrationService $registrationService,
    ): Response {
        $data = new RegistrationData();
        $form = $this->createForm(RegistrationFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $registrationService->register($data);
            } catch (\DomainException) {
                $form->get('email')->addError(new FormError('Cette adresse e-mail est déjà utilisée.'));

                return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
            }

            return $this->render('registration/check_email.html.twig');
        }

        return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, EntityManagerInterface $entityManager, VerifyEmailHelperInterface $verifyEmailHelper): Response
    {
        $id = $request->query->getString('id');
        $user = ctype_digit($id) ? $entityManager->getRepository(User::class)->find((int) $id) : null;
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        $user->setVerified(true);
        $entityManager->flush();

        $this->addFlash('success', 'Votre adresse e-mail est vérifiée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}
