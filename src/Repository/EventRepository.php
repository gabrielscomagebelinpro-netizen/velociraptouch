<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

final class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.date >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'), Types::DATE_IMMUTABLE)
            ->orderBy('event.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Événements à partir d'une date (incluse), du plus ancien au plus récent. */
    public function findSince(\DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.date >= :from')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->orderBy('event.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Événements passés (avant aujourd'hui), du plus récent au plus ancien. */
    public function findPast(): array
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.date < :today')
            ->setParameter('today', new \DateTimeImmutable('today'), Types::DATE_IMMUTABLE)
            ->orderBy('event.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
