<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Comptes portant ce prénom et ce nom, sans tenir compte des majuscules ni des espaces autour.
     * La connexion se fait par prénom + nom : deux homonymes bloqueraient la connexion de l'un et de l'autre.
     *
     * @param array{firstName?: string, lastName?: string} $criteria
     *
     * @return list<User>
     */
    public function findByNameCaseInsensitive(array $criteria): array
    {
        return $this->createQueryBuilder('user')
            ->where('LOWER(TRIM(user.firstName)) = LOWER(TRIM(:firstName))')
            ->andWhere('LOWER(TRIM(user.lastName)) = LOWER(TRIM(:lastName))')
            ->setParameter('firstName', (string) ($criteria['firstName'] ?? ''))
            ->setParameter('lastName', (string) ($criteria['lastName'] ?? ''))
            ->getQuery()
            ->getResult();
    }
}
