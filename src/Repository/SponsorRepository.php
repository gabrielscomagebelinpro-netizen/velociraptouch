<?php

namespace App\Repository;

use App\Entity\Sponsor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sponsor>
 */
final class SponsorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sponsor::class);
    }

    /**
     * Partenaires rangés par emplacement (dans l'ordre de Sponsor::TIERS), puis par position.
     *
     * @return array<string, list<Sponsor>>
     */
    public function findGroupedByTier(): array
    {
        $grouped = array_fill_keys(array_keys(Sponsor::TIERS), []);
        foreach ($this->findBy([], ['position' => 'ASC', 'id' => 'ASC']) as $sponsor) {
            $grouped[$sponsor->getTier()][] = $sponsor;
        }

        return $grouped;
    }

    /** @return list<Sponsor> */
    public function findInTier(string $tier): array
    {
        return $this->findBy(['tier' => $tier], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function nextPosition(string $tier): int
    {
        $max = $this->createQueryBuilder('sponsor')
            ->select('MAX(sponsor.position)')
            ->where('sponsor.tier = :tier')
            ->setParameter('tier', $tier)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
