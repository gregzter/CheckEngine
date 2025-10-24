<?php

namespace App\Repository;

use App\Entity\OBD2ColumnVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OBD2ColumnVariant>
 */
class OBD2ColumnVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OBD2ColumnVariant::class);
    }

    /**
     * Trouve une variante par son nom exact
     */
    public function findByVariantName(string $variantName): ?OBD2ColumnVariant
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.column', 'c')
            ->where('LOWER(v.variantName) = LOWER(:name)')
            ->andWhere('v.active = :active')
            ->andWhere('c.active = :active')
            ->setParameter('name', $variantName)
            ->setParameter('active', true)
            ->orderBy('v.priority', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère toutes les variantes actives pour construire le mapping
     */
    public function getAllActiveMappings(): array
    {
        $variants = $this->createQueryBuilder('v')
            ->innerJoin('v.column', 'c')
            ->addSelect('c')
            ->where('v.active = :active')
            ->andWhere('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('v.priority', 'ASC')
            ->getQuery()
            ->getResult();

        // Construire un mapping sous forme de tableau
        $mapping = [];
        foreach ($variants as $variant) {
            $columnName = $variant->getColumn()->getName();
            if (!isset($mapping[$columnName])) {
                $mapping[$columnName] = [];
            }
            $mapping[$columnName][] = [
                'variant' => $variant->getVariantName(),
                'priority' => $variant->getPriority(),
                'source' => $variant->getSource(),
            ];
        }

        return $mapping;
    }
}
