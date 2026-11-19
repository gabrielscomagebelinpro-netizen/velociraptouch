<?php

namespace App\Repository;

use App\Entity\PhotoAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PhotoAlbum> */
final class PhotoAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhotoAlbum::class);
    }

    /** @return list<PhotoAlbum> */
    public function findNewest(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }
}
