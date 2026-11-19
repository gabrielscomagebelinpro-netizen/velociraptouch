<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\PhotoAlbum;
use App\Entity\EventPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class EventPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventPhoto::class);
    }

    // Ancienne API conservée uniquement pour les contrôleurs liés aux événements encore en transition.
    public function findForEvent(Event $event): array
    {
        return $this->findBy(['event' => $event], ['createdAt' => 'DESC']);
    }

    public function countByEvent(): array
    {
        $rows = $this->createQueryBuilder('photo')
            ->select('IDENTITY(photo.event) AS eventId', 'COUNT(photo.id) AS photoCount')
            ->where('photo.event IS NOT NULL')
            ->groupBy('photo.event')
            ->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) $counts[(int) $row['eventId']] = (int) $row['photoCount'];
        return $counts;
    }

    public function findForAlbum(PhotoAlbum $album): array
    {
        return $this->findBy(['album' => $album], ['createdAt' => 'DESC']);
    }

    /** @return array<int, int> */
    public function countByAlbum(): array
    {
        $rows = $this->createQueryBuilder('photo')
            ->select('IDENTITY(photo.album) AS albumId', 'COUNT(photo.id) AS photoCount')
            ->groupBy('photo.album')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['albumId']] = (int) $row['photoCount'];
        }

        return $counts;
    }
}
