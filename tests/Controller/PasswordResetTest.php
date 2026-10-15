<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetTest extends DatabaseWebTestCase
{
    public function testResetLinkChangesThePassword(): void
    {
        $user = $this->createVerifiedUser('reset@example.test', 'Reset User');
        $userId = $user->getId();
        static::ensureKernelShutdown();
        $client = static::createClient();
        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->selectButton('Envoyer le lien')->form([
            'reset_password_request_form[email]' => 'reset@example.test',
        ]));
        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1);

        $email = static::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        $html = $email->getHtmlBody();
        self::assertIsString($html);
        self::assertMatchesRegularExpression('/href="([^"]*\/reset-password\/reset\/[^" ]+)"/', $html);
        preg_match('/href="([^"]*\/reset-password\/reset\/[^" ]+)"/', $html, $matches);
        $client->request('GET', html_entity_decode($matches[1], ENT_QUOTES));
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $form = $client->getCrawler()->selectButton('Enregistrer le nouveau mot de passe')->form([
            'change_password_form[plainPassword][first]' => 'a-new-and-strong-password',
            'change_password_form[plainPassword][second]' => 'a-new-and-strong-password',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/teams');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'a-new-and-strong-password'));

        $client->request('GET', html_entity_decode($matches[1], ENT_QUOTES));
        $client->followRedirect();
        self::assertResponseRedirects('/reset-password');
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'a-new-and-strong-password'));
    }

    public function testUnknownEmailGetsTheSameConfirmationWithoutSendingAnEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->selectButton('Envoyer le lien')->form([
            'reset_password_request_form[email]' => 'unknown@example.test',
        ]));
        self::assertResponseRedirects('/reset-password/check-email');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'If an account matching your email exists');
        self::assertEmailCount(0);
    }
}
