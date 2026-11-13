<?php
namespace App\Repository;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Product::class); }
    /** @return list<Product> */
    public function findActive(): array { return $this->createQueryBuilder('p')->andWhere('p.active = true')->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC')->getQuery()->getResult(); }
    /** @return list<Product> */
    public function findAllOrdered(): array { return $this->createQueryBuilder('p')->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC')->getQuery()->getResult(); }
}
