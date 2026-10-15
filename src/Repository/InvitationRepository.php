<?php

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Invitation> */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findPendingForEmail(Team $team, string $email): ?Invitation
    {
        return $this->createQueryBuilder('invitation')
            ->andWhere('invitation.team = :team')
            ->andWhere('invitation.email = :email')
            ->andWhere('invitation.acceptedAt IS NULL')
            ->andWhere('invitation.revokedAt IS NULL')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('team', $team)
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
