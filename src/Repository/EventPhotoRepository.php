<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class EventPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventPhoto::class);
    }

    public function findForEvent(Event $event): array
    {
        return $this->findBy(['event' => $event], ['createdAt' => 'DESC']);
    }

    /**
     * Nombre de photos par identifiant d'événement (les événements sans photo sont absents).
     *
     * @return array<int, int>
     */
    public function countByEvent(): array
    {
        $rows = $this->createQueryBuilder('photo')
            ->select('IDENTITY(photo.event) AS eventId', 'COUNT(photo.id) AS photoCount')
            ->groupBy('photo.event')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['eventId']] = (int) $row['photoCount'];
        }

        return $counts;
    }

    /**
     * Événements ayant au moins une photo, du plus récent au plus ancien, avec leur nombre de photos.
     *
     * @return list<array{event: Event, photoCount: int}>
     */
    public function findEventsWithPhotos(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('event AS eventEntity', 'COUNT(photo.id) AS photoCount')
            ->from(Event::class, 'event')
            ->join(EventPhoto::class, 'photo', 'WITH', 'photo.event = event')
            ->groupBy('event.id')
            ->orderBy('event.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['event' => $row['eventEntity'], 'photoCount' => (int) $row['photoCount']],
            $rows,
        );
    }
}
