<?php

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseWebTestCase;

final class HomeControllerTest extends DatabaseWebTestCase
{
    public function testAnonymousVisitorLogsInAndReachesTeamsFromHome(): void
    {
        $this->createVerifiedUser('home@example.test', 'Home User');
        self::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/register"]');

        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'home@example.test',
            '_password' => 'correct horse battery staple',
        ]));
        self::assertResponseRedirects('http://localhost/');
        $client->followRedirect();
        self::assertResponseRedirects('/teams');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes équipes');
    }

    public function testAuthenticatedVisitorIsRedirectedToTeams(): void
    {
        $user = $this->createVerifiedUser('member@example.test', 'Member');
        self::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/');
        self::assertResponseRedirects('/teams');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes équipes');
    }

    public function testDirectLoginDefaultsToHomeAndTeams(): void
    {
        $this->createVerifiedUser('login@example.test', 'Login User');
        self::ensureKernelShutdown();

        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'login@example.test',
            '_password' => 'correct horse battery staple',
        ]));
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseRedirects('/teams');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLoginPreservesTheRequestedTeamPage(): void
    {
        $user = $this->createVerifiedUser('owner@example.test', 'Owner');
        $team = $this->createTeam($user, 'Requested Team');
        $teamPath = '/teams/'.$team->getId();
        self::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('GET', $teamPath);
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'owner@example.test',
            '_password' => 'correct horse battery staple',
        ]));
        self::assertResponseRedirects('http://localhost'.$teamPath);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Requested Team');
    }
}
