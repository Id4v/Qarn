<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Team> */
final class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /** @return list<Team> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('team')
            ->innerJoin('team.memberships', 'membership')
            ->andWhere('membership.user = :user')
            ->setParameter('user', $user)
            ->orderBy('team.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
