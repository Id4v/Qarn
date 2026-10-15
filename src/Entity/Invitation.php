<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'team_invitation')]
class Invitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'invitations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Team $team,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $invitedBy,
        string $email,
        #[ORM\Column(name: 'token_hash', length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->email = strtolower(trim($email));
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isPending(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return null === $this->acceptedAt && null === $this->revokedAt && $this->expiresAt > $now;
    }

    public function accept(\DateTimeImmutable $now = new \DateTimeImmutable()): void
    {
        $this->acceptedAt = $now;
    }

    public function revoke(\DateTimeImmutable $now = new \DateTimeImmutable()): void
    {
        $this->revokedAt = $now;
    }
}
