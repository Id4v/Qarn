<?php

namespace App\Tests\Controller;

use App\Entity\Membership;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\TeamRepository;
use App\Service\InvitationLinkService;
use App\Service\TeamManager;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

final class RegistrationControllerTest extends DatabaseWebTestCase
{
    public function testRegistrationCreatesAnUnverifiedAccountAndPreventsLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'New.User@example.test',
            'registration_form[displayName]' => 'New User',
            'registration_form[plainPassword][first]' => 'correct horse battery staple',
            'registration_form[plainPassword][second]' => 'correct horse battery staple',
        ]);
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vérifiez votre adresse e-mail');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'new.user@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
        self::assertSame('New User', $user->getDisplayName());

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'new.user@example.test',
            '_password' => 'correct horse battery staple',
        ]));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vérifiez votre adresse e-mail avant de vous connecter.');
        $client->request('GET', '/teams');
        self::assertResponseRedirects('/login');
    }

    public function testSignedConfirmationLinkVerifiesTheRegisteredEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'verify@example.test',
            'registration_form[displayName]' => 'Verify Me',
            'registration_form[plainPassword][first]' => 'correct horse battery staple',
            'registration_form[plainPassword][second]' => 'correct horse battery staple',
        ]));
        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);

        $confirmationEmail = static::getMailerMessage();
        self::assertInstanceOf(Email::class, $confirmationEmail);
        self::assertIsString($confirmationEmail->getHtmlBody());
        self::assertMatchesRegularExpression('/href="([^"]*\/verify\/email\?[^"]+)"/', $confirmationEmail->getHtmlBody());
        preg_match('/href="([^"]*\/verify\/email\?[^"]+)"/', $confirmationEmail->getHtmlBody(), $matches);
        $client->request('GET', html_entity_decode($matches[1], ENT_QUOTES));
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'verify@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified());
    }

    public function testInviteeCanRegisterVerifyLoginAndAcceptInvitation(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $team = $this->createTeam($owner, 'Invite Flow Team');
        $teamId = $team->getId();
        $invitationData = static::getContainer()->get(TeamManager::class)->invite($owner, $team, 'new.invitee@example.test');
        $signedLink = static::getContainer()->get(InvitationLinkService::class)->create($invitationData['invitation'], $invitationData['token']);
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('GET', $signedLink);
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        self::assertSelectorExists('a[href="/register"]');
        $crawler = $client->click($crawler->selectLink('Inscrivez-vous')->link());
        $client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'new.invitee@example.test',
            'registration_form[displayName]' => 'New Invitee',
            'registration_form[plainPassword][first]' => 'correct horse battery staple',
            'registration_form[plainPassword][second]' => 'correct horse battery staple',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vérifiez votre adresse e-mail');
        self::assertEmailCount(1);

        $verificationEmail = static::getMailerMessage();
        self::assertInstanceOf(Email::class, $verificationEmail);
        $verificationBody = $verificationEmail->getHtmlBody();
        self::assertIsString($verificationBody);
        preg_match('/href="([^"]*\/verify\/email\?[^"]+)"/', $verificationBody, $verificationMatches);
        self::assertArrayHasKey(1, $verificationMatches);
        $client->request('GET', html_entity_decode($verificationMatches[1], ENT_QUOTES));
        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'new.invitee@example.test',
            '_password' => 'correct horse battery staple',
        ]));
        self::assertResponseRedirects($signedLink);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Rejoindre Invite Flow Team');
        $client->submit($crawler->selectButton('Rejoindre l’équipe')->form());
        self::assertResponseRedirects('/teams');

        self::ensureKernelShutdown();
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $invitee = $entityManager->getRepository(User::class)->findOneBy(['email' => 'new.invitee@example.test']);
        self::assertInstanceOf(User::class, $invitee);
        self::assertTrue($invitee->isVerified());
        $team = static::getContainer()->get(TeamRepository::class)->find($teamId);
        self::assertInstanceOf(Team::class, $team);
        $membership = static::getContainer()->get(MembershipRepository::class)->findFor($team, $invitee);
        self::assertInstanceOf(Membership::class, $membership);
        self::assertSame(MembershipRole::MEMBER, $membership->getRole());
        self::assertNotNull(static::getContainer()->get(InvitationRepository::class)->find($invitationData['invitation']->getId())?->getAcceptedAt());
        $client->request('GET', $signedLink);
        self::assertResponseStatusCodeSame(404);
    }
}
