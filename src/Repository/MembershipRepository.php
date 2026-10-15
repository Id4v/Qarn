<?php

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MembershipRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Membership> */
final class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    public function findFor(Team $team, User $user): ?Membership
    {
        return $this->findOneBy(['team' => $team, 'user' => $user]);
    }

    public function countOwners(Team $team): int
    {
        return $this->count(['team' => $team, 'role' => MembershipRole::OWNER]);
    }
}
