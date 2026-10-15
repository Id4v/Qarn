<?php

namespace App\Entity;

use App\Enum\MembershipRole;
use App\Repository\MembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'team_membership')]
#[ORM\UniqueConstraint(name: 'uniq_membership_team_user', columns: ['team_id', 'user_id'])]
class Membership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct(#[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'memberships')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Team $team, #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user, #[ORM\Column(enumType: MembershipRole::class, length: 16)]
        private MembershipRole $role = MembershipRole::MEMBER)
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): MembershipRole
    {
        return $this->role;
    }

    public function setRole(MembershipRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
