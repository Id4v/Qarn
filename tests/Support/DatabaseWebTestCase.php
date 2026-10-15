<?php

namespace App\Tests\Support;

use App\Entity\Team;
use App\Entity\User;
use App\Service\TeamManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        self::ensureKernelShutdown();
    }

    protected function createVerifiedUser(string $email, string $displayName): User
    {
        $user = new User()->setEmail($email)->setDisplayName($displayName)->setVerified(true);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'correct horse battery staple'));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function createTeam(User $owner, string $name = 'Example Team'): Team
    {
        return self::getContainer()->get(TeamManager::class)->createTeam($owner, $name);
    }
}
