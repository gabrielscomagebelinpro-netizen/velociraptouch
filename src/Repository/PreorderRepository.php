<?php
namespace App\Repository;
use App\Entity\Preorder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class PreorderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Preorder::class); }
    /** @return list<Preorder> */
    public function findRecent(): array { return $this->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC')->getQuery()->getResult(); }
    /** @return list<Preorder> */
    public function findForUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('o')->andWhere('o.user = :user')->setParameter('user', $user)->orderBy('o.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function findForUserAndProduct(\App\Entity\User $user, \App\Entity\Product $product): ?Preorder
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }
}
