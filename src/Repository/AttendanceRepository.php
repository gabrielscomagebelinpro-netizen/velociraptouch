<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    public function findForUserAndEvents(User $user, array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->createQueryBuilder('attendance')
            ->andWhere('attendance.user = :user')
            ->andWhere('attendance.event IN (:events)')
            ->setParameter('user', $user)
            ->setParameter('events', $events)
            ->getQuery()
            ->getResult();
    }

    public function findForEvent(Event $event): array
    {
        return $this->findBy(['event' => $event], ['createdAt' => 'ASC']);
    }

    public function removeForPastEvents(\DateTimeImmutable $today): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM event_attendances WHERE event_id IN (SELECT id FROM events WHERE date < ?)',
            [$today->format('Y-m-d')],
        );
    }
}
