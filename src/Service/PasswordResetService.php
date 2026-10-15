<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {
    }

    public function requestReset(string $email): ?ResetPasswordToken
    {
        $user = $this->users->findOneBy(['email' => strtolower(trim($email))]);
        if (!$user instanceof User) {
            return null;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return null;
        }

        $message = new TemplatedEmail()
            ->from(new Address('no-reply@qarn.local', 'Qarn'))
            ->to((string) $user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context(['resetToken' => $resetToken]);
        $this->mailer->send($message);

        return $resetToken;
    }

    public function createFakeToken(): ResetPasswordToken
    {
        return $this->resetPasswordHelper->generateFakeResetToken();
    }

    public function changePassword(User $user, string $token, string $plainPassword): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $token, $plainPassword): void {
            // The bundle validates this token before the form is shown and checks it again here.
            // Keeping token removal and password update in one transaction preserves one-time use.
            $this->resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();
        });
    }
}
