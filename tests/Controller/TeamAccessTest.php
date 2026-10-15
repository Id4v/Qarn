<?php

namespace App\Tests\Controller;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\TeamRepository;
use App\Service\TeamManager;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

final class TeamAccessTest extends DatabaseWebTestCase
{
    public function testMemberCanViewTeamButCannotInvite(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $member = $this->createVerifiedUser('member@example.test', 'Member');
        $team = $this->createTeam($owner);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Membership($team, $member, MembershipRole::MEMBER));
        $entityManager->flush();
        $teamId = $team->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($member);
        $client->request('GET', '/teams/'.$teamId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Example Team');

        $client->request('POST', '/teams/'.$teamId.'/invitations');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAccountCanCreateATeamAndPromoteThenDemoteAMember(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $member = $this->createVerifiedUser('member@example.test', 'Member');
        $memberId = $member->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/teams');
        $client->submit($crawler->selectButton('Créer l’équipe')->form(['team_form[name]' => 'Product Team']));
        self::assertResponseRedirects('/teams');

        $team = static::getContainer()->get(TeamRepository::class)->findOneBy(['name' => 'Product Team']);
        self::assertInstanceOf(Team::class, $team);
        $teamId = $team->getId();
        $memberships = static::getContainer()->get(MembershipRepository::class);
        $ownerMembership = $memberships->findFor($team, $owner);
        self::assertInstanceOf(Membership::class, $ownerMembership);
        self::assertSame(MembershipRole::OWNER, $ownerMembership->getRole());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $managedMember = $entityManager->find(User::class, $memberId);
        self::assertInstanceOf(User::class, $managedMember);
        $entityManager->persist(new Membership($team, $managedMember, MembershipRole::MEMBER));
        $entityManager->flush();
        $memberMembership = $memberships->findFor($team, $managedMember);
        self::assertInstanceOf(Membership::class, $memberMembership);
        $memberMembershipId = $memberMembership->getId();

        $crawler = $client->request('GET', '/teams/'.$teamId);
        $promoteForm = $crawler->filter('form[action="/teams/'.$teamId.'/members/'.$memberMembershipId.'/promote"]')->form();
        $client->submit($promoteForm);
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertSame(MembershipRole::OWNER, static::getContainer()->get(MembershipRepository::class)->find($memberMembershipId)?->getRole());

        $crawler = $client->request('GET', '/teams/'.$teamId);
        $demoteForm = $crawler->filter('form[action="/teams/'.$teamId.'/members/'.$memberMembershipId.'/demote"]')->form();
        $client->submit($demoteForm);
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertSame(MembershipRole::MEMBER, static::getContainer()->get(MembershipRepository::class)->find($memberMembershipId)?->getRole());
    }

    public function testTheSameAccountGetsDifferentPermissionsInEachTeam(): void
    {
        $account = $this->createVerifiedUser('multi-role@example.test', 'Multi Role');
        $otherOwner = $this->createVerifiedUser('other-owner@example.test', 'Other Owner');
        $ownedTeam = $this->createTeam($account, 'Owned Team');
        $memberTeam = $this->createTeam($otherOwner, 'Member Team');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Membership($memberTeam, $account, MembershipRole::MEMBER));
        $entityManager->flush();
        $ownedTeamId = $ownedTeam->getId();
        $memberTeamId = $memberTeam->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($account);
        $crawler = $client->request('GET', '/teams/'.$ownedTeamId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Inviter une personne');
        $crawler = $client->request('GET', '/teams/'.$memberTeamId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Inviter une personne');
        $client->request('POST', '/teams/'.$memberTeamId.'/invitations');
        self::assertResponseStatusCodeSame(403);

        self::ensureKernelShutdown();
        self::bootKernel();
        $memberships = static::getContainer()->get(MembershipRepository::class);
        $ownedTeam = static::getContainer()->get(TeamRepository::class)->find($ownedTeamId);
        $memberTeam = static::getContainer()->get(TeamRepository::class)->find($memberTeamId);
        self::assertInstanceOf(Team::class, $ownedTeam);
        self::assertInstanceOf(Team::class, $memberTeam);
        self::assertSame(MembershipRole::OWNER, $memberships->findFor($ownedTeam, $account)?->getRole());
        self::assertSame(MembershipRole::MEMBER, $memberships->findFor($memberTeam, $account)?->getRole());
    }

    public function testOwnerCanManageOtherOwnersWithoutLeavingTheTeamOwnerless(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $otherOwner = $this->createVerifiedUser('other-owner@example.test', 'Other Owner');
        $removableOwner = $this->createVerifiedUser('removable-owner@example.test', 'Removable Owner');
        $promotableMember = $this->createVerifiedUser('promotable@example.test', 'Promotable');
        $team = $this->createTeam($owner, 'Ownership Team');
        $teamId = $team->getId();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([[$otherOwner, MembershipRole::OWNER], [$removableOwner, MembershipRole::OWNER], [$promotableMember, MembershipRole::MEMBER]] as [$user, $role]) {
            $entityManager->persist(new Membership($team, $user, $role));
        }
        $entityManager->flush();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($owner);
        $memberships = static::getContainer()->get(MembershipRepository::class);
        $otherOwnerMembership = $memberships->findFor($team, $otherOwner);
        $removableMembership = $memberships->findFor($team, $removableOwner);
        $promotableMembership = $memberships->findFor($team, $promotableMember);
        self::assertInstanceOf(Membership::class, $otherOwnerMembership);
        self::assertInstanceOf(Membership::class, $removableMembership);
        self::assertInstanceOf(Membership::class, $promotableMembership);

        $crawler = $client->request('GET', '/teams/'.$teamId);
        $client->submit($crawler->filter('form[action="/teams/'.$teamId.'/members/'.$promotableMembership->getId().'/promote"]')->form());
        self::assertResponseRedirects('/teams/'.$teamId);

        $crawler = $client->request('GET', '/teams/'.$teamId);
        $client->submit($crawler->filter('form[action="/teams/'.$teamId.'/members/'.$otherOwnerMembership->getId().'/demote"]')->form());
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertSame(MembershipRole::MEMBER, $memberships->find($otherOwnerMembership->getId())?->getRole());

        $crawler = $client->request('GET', '/teams/'.$teamId);
        $client->submit($crawler->filter('form[action="/teams/'.$teamId.'/members/'.$removableMembership->getId().'/remove"]')->form());
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertNull($memberships->find($removableMembership->getId()));

        self::assertSame(2, $memberships->countOwners($team));
        $crawler = $client->request('GET', '/teams/'.$teamId);
        $lastOwnerForm = $crawler->filter('form[action="/teams/'.$teamId.'/members/'.$promotableMembership->getId().'/demote"]')->form();
        $client->submit($lastOwnerForm);
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertSame(1, $memberships->countOwners($team));

        $ownerMembership = $memberships->findFor($team, $owner);
        self::assertInstanceOf(Membership::class, $ownerMembership);
        $teamManager = static::getContainer()->get(TeamManager::class);
        try {
            $teamManager->demote($owner, $team, $ownerMembership);
            self::fail('The last owner must not be demoted.');
        } catch (\DomainException) {
            self::assertSame(1, $memberships->countOwners($team));
        }
        try {
            $teamManager->removeMember($owner, $team, $ownerMembership);
            self::fail('The last owner must not be removed.');
        } catch (\DomainException) {
            self::assertSame(1, $memberships->countOwners($team));
        }
        self::ensureKernelShutdown();
        self::bootKernel();
        $team = static::getContainer()->get(TeamRepository::class)->find($teamId);
        self::assertInstanceOf(Team::class, $team);
        self::assertSame(1, static::getContainer()->get(MembershipRepository::class)->countOwners($team));
    }

    public function testLastOwnerCannotLeaveButCanDeleteTheTeamAfterConfirmation(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $team = $this->createTeam($owner);
        $teamId = $team->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/teams/'.$teamId);
        $leaveForm = $crawler->filter('form[action="/teams/'.$teamId.'/leave"]')->form();
        $client->submit($leaveForm);
        self::assertResponseRedirects('/teams');

        $team = static::getContainer()->get(TeamRepository::class)->find($teamId);
        self::assertInstanceOf(Team::class, $team);
        $crawler = $client->request('GET', '/teams/'.$teamId);
        $deleteForm = $crawler->filter('form[action="/teams/'.$teamId.'/delete"]')->form([
            'confirmation' => 'Example Team',
        ]);
        $client->submit($deleteForm);
        self::assertResponseRedirects('/teams');

        self::ensureKernelShutdown();
        self::bootKernel();
        self::assertNull(static::getContainer()->get(TeamRepository::class)->find($teamId));
    }

    public function testSignedInvitationCanBeAcceptedOnlyOnceAndGrantsMemberRole(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $invitee = $this->createVerifiedUser('invitee@example.test', 'Invitee');
        $team = $this->createTeam($owner);
        $result = static::getContainer()->get(TeamManager::class)->invite($owner, $team, 'invitee@example.test');
        $link = static::getContainer()->get(\App\Service\InvitationLinkService::class)->create($result['invitation'], $result['token']);
        $teamId = $team->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($invitee);
        $crawler = $client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Rejoindre Example Team');
        $client->submit($crawler->selectButton('Rejoindre l’équipe')->form());
        self::assertResponseRedirects('/teams');

        self::ensureKernelShutdown();
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $team = static::getContainer()->get(TeamRepository::class)->find($teamId);
        self::assertInstanceOf(Team::class, $team);
        $membership = static::getContainer()->get(MembershipRepository::class)->findFor($team, $invitee);
        self::assertInstanceOf(Membership::class, $membership);
        self::assertSame(MembershipRole::MEMBER, $membership->getRole());

        $client->request('GET', $link);
        self::assertResponseStatusCodeSame(404);
        self::ensureKernelShutdown();
    }

    public function testOwnerCanSendAndRevokeAnEmailBoundInvitation(): void
    {
        $owner = $this->createVerifiedUser('owner@example.test', 'Owner');
        $invitee = $this->createVerifiedUser('invitee@example.test', 'Invitee');
        $team = $this->createTeam($owner);
        $teamId = $team->getId();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/teams/'.$teamId);
        $inviteForm = $crawler->filter('form[action="/teams/'.$teamId.'/invitations"]')->form([
            'invitation_form[email]' => 'invitee@example.test',
        ]);
        $client->submit($inviteForm);
        self::assertResponseRedirects('/teams/'.$teamId);
        self::assertEmailCount(1);
        $email = static::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'to', 'invitee@example.test');
        $html = $email->getHtmlBody();
        self::assertIsString($html);
        self::assertMatchesRegularExpression('/href="([^"]*\/invitations\/accept\/[^" ]+)"/', $html);
        preg_match('/href="([^"]*\/invitations\/accept\/[^" ]+)"/', $html, $matches);

        $invitation = static::getContainer()->get(InvitationRepository::class)->findOneBy(['email' => 'invitee@example.test']);
        self::assertInstanceOf(Invitation::class, $invitation);
        self::assertSame(14, (int) $invitation->getCreatedAt()->diff($invitation->getExpiresAt())->days);
        $crawler = $client->request('GET', '/teams/'.$teamId);
        $revokeForm = $crawler->filter('form[action="/teams/'.$teamId.'/invitations/'.$invitation->getId().'/revoke"]')->form();
        $client->submit($revokeForm);
        self::assertResponseRedirects('/teams/'.$teamId);

        $client->loginUser($invitee);
        $client->request('GET', html_entity_decode($matches[1], ENT_QUOTES));
        self::assertResponseStatusCodeSame(404);
    }
}
