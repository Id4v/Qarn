<?php

namespace App\Security;

use App\Entity\Team;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Team> */
final class TeamVoter extends Voter
{
    public const string VIEW = 'TEAM_VIEW';
    public const string MANAGE = 'TEAM_MANAGE';
    public const string DELETE = 'TEAM_DELETE';

    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Team && in_array($attribute, [self::VIEW, self::MANAGE, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isVerified()) {
            return false;
        }

        $membership = $this->memberships->findFor($subject, $user);
        if (!$membership) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::MANAGE => MembershipRole::OWNER === $membership->getRole(),
            self::DELETE => MembershipRole::OWNER === $membership->getRole()
                && 1 === $this->memberships->countOwners($subject),
            default => false,
        };
    }
}
