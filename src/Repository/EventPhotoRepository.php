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
}
