<?php

namespace App\Repository;

use App\Entity\OBD2Column;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OBD2Column>
 */
class OBD2ColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OBD2Column::class);
    }

    /**
     * Récupère toutes les colonnes actives avec leurs variantes
     */
    public function findAllActiveWithVariants(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.variants', 'v')
            ->addSelect('v')
            ->where('c.active = :active')
            ->andWhere('v.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.category', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère une colonne par son nom normalisé
     */
    public function findByName(string $name): ?OBD2Column
    {
        return $this->findOneBy(['name' => $name, 'active' => true]);
    }

    /**
     * Récupère toutes les colonnes d'une catégorie
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category, 'active' => true], ['name' => 'ASC']);
    }
}
