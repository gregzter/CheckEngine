<?php

namespace App\Repository;

use App\Entity\TripDiagnostic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TripDiagnostic>
 */
class TripDiagnosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TripDiagnostic::class);
    }

    /**
     * Find diagnostics by trip and category
     *
     * @return TripDiagnostic[]
     */
    public function findByTripAndCategory(int $tripId, string $category): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.trip = :tripId')
            ->andWhere('d.category = :category')
            ->setParameter('tripId', $tripId)
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get latest diagnostics for a trip
     *
     * @return TripDiagnostic[]
     */
    public function getLatestDiagnostics(int $tripId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.trip = :tripId')
            ->setParameter('tripId', $tripId)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
