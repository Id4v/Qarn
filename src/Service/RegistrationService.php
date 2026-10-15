<?php

namespace App\Service;

use App\Dto\RegistrationData;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final readonly class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
    ) {
    }

    public function register(RegistrationData $data): void
    {
        $email = strtolower(trim($data->email));
        if ($this->users->findOneBy(['email' => $email])) {
            throw new \DomainException('Cette adresse e-mail est déjà utilisée.');
        }

        $user = new User()
            ->setEmail($email)
            ->setDisplayName($data->displayName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );
        $email = new TemplatedEmail()
            ->from(new Address('no-reply@qarn.local', 'Qarn'))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre adresse e-mail sur Qarn')
            ->htmlTemplate('registration/verification_email.html.twig')
            ->context(['signedUrl' => $signature->getSignedUrl(), 'expiresAt' => $signature->getExpiresAt()]);
        $this->mailer->send($email);
    }
}
