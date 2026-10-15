<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'team')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'team', orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, Invitation> */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'team', orphanRemoval: true)]
    private Collection $invitations;

    public function __construct(string $name)
    {
        $this->name = trim($name);
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /** @return Collection<int, Invitation> */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }
}
