<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\TripData;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for bulk operations on TripData (time-series)
 * Uses DBAL for high-performance bulk inserts
 */
class TripDataService
{
    private const BULK_INSERT_BATCH_SIZE = 1000;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Bulk insert trip data points using DBAL for maximum performance
     *
     * @param array<array{timestamp: \DateTimeImmutable, pid_name: string, value: ?float, unit: ?string}> $dataPoints
     */
    public function bulkInsert(Trip $trip, array $dataPoints): int
    {
        if (empty($dataPoints)) {
            return 0;
        }

        $tripId = $trip->getId();
        $inserted = 0;
        $batch = [];

        foreach ($dataPoints as $point) {
            $batch[] = [
                'trip_id' => $tripId,
                'timestamp' => $point['timestamp']->format('Y-m-d H:i:s'),
                'pid_name' => $point['pid_name'],
                'value' => $point['value'],
                'unit' => $point['unit'] ?? null,
            ];

            if (count($batch) >= self::BULK_INSERT_BATCH_SIZE) {
                $inserted += $this->executeBulkInsert($batch);
                $batch = [];
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            $inserted += $this->executeBulkInsert($batch);
        }

        return $inserted;
    }

    /**
     * Execute bulk insert for a batch
     */
    private function executeBulkInsert(array $batch): int
    {
        if (empty($batch)) {
            return 0;
        }

        $values = [];
        $params = [];
        $types = [];
        $paramIndex = 1;

        foreach ($batch as $row) {
            $placeholders = [];
            foreach (['trip_id', 'timestamp', 'pid_name', 'value', 'unit'] as $col) {
                $placeholders[] = '?';
                $params[] = $row[$col];
                $types[] = match ($col) {
                    'trip_id' => \PDO::PARAM_INT,
                    'value' => \PDO::PARAM_STR, // DECIMAL as string
                    default => \PDO::PARAM_STR,
                };
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO trip_data (trip_id, timestamp, pid_name, value, unit) VALUES %s',
            implode(', ', $values)
        );

        return $this->connection->executeStatement($sql, $params, $types);
    }

    /**
     * Get time-series data for a trip with optional filtering
     *
     * @param array<string> $pidNames Filter by PID names (empty = all)
     * @return array<array{timestamp: string, pid_name: string, value: ?string, unit: ?string}>
     */
    public function getTimeSeriesData(
        Trip $trip,
        array $pidNames = [],
        ?\DateTimeImmutable $startTime = null,
        ?\DateTimeImmutable $endTime = null
    ): array {
        $qb = $this->connection->createQueryBuilder()
            ->select('timestamp', 'pid_name', 'value', 'unit')
            ->from('trip_data')
            ->where('trip_id = :tripId')
            ->setParameter('tripId', $trip->getId())
            ->orderBy('timestamp', 'ASC');

        if (!empty($pidNames)) {
            $qb->andWhere('pid_name IN (:pidNames)')
                ->setParameter('pidNames', $pidNames, Connection::PARAM_STR_ARRAY);
        }

        if ($startTime) {
            $qb->andWhere('timestamp >= :startTime')
                ->setParameter('startTime', $startTime->format('Y-m-d H:i:s'));
        }

        if ($endTime) {
            $qb->andWhere('timestamp <= :endTime')
                ->setParameter('endTime', $endTime->format('Y-m-d H:i:s'));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get aggregated statistics using TimescaleDB time_bucket
     *
     * @return array<array{time_bucket: string, pid_name: string, avg: float, min: float, max: float, count: int}>
     */
    public function getAggregatedStats(
        Trip $trip,
        string $interval = '1 minute',
        array $pidNames = []
    ): array {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                "time_bucket(:interval, timestamp) as time_bucket",
                'pid_name',
                'AVG(value::float) as avg',
                'MIN(value::float) as min',
                'MAX(value::float) as max',
                'COUNT(*) as count'
            )
            ->from('trip_data')
            ->where('trip_id = :tripId')
            ->setParameter('tripId', $trip->getId())
            ->setParameter('interval', $interval)
            ->groupBy('time_bucket', 'pid_name')
            ->orderBy('time_bucket', 'ASC');

        if (!empty($pidNames)) {
            $qb->andWhere('pid_name IN (:pidNames)')
                ->setParameter('pidNames', $pidNames, ArrayParameterType::STRING);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get downsampled data (first/last value per time bucket)
     *
     * @return array<array{time_bucket: string, pid_name: string, first_value: float, last_value: float}>
     */
    public function getDownsampledData(
        Trip $trip,
        string $interval = '5 seconds',
        array $pidNames = []
    ): array {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                "time_bucket(:interval, timestamp) as time_bucket",
                'pid_name',
                'first(value::float, timestamp) as first_value',
                'last(value::float, timestamp) as last_value'
            )
            ->from('trip_data')
            ->where('trip_id = :tripId')
            ->setParameter('tripId', $trip->getId())
            ->setParameter('interval', $interval)
            ->groupBy('time_bucket', 'pid_name')
            ->orderBy('time_bucket', 'ASC');

        if (!empty($pidNames)) {
            $qb->andWhere('pid_name IN (:pidNames)')
                ->setParameter('pidNames', $pidNames, ArrayParameterType::STRING);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Delete all data for a trip (cascade will handle this, but explicit method for clarity)
     */
    public function deleteTripData(Trip $trip): int
    {
        return $this->connection->delete('trip_data', ['trip_id' => $trip->getId()]);
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats(Trip $trip): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_points,
                COUNT(DISTINCT pid_name) as unique_pids,
                MIN(timestamp) as first_timestamp,
                MAX(timestamp) as last_timestamp,
                pg_size_pretty(pg_total_relation_size('trip_data')) as total_size
            FROM trip_data
            WHERE trip_id = :tripId
        ";

        return $this->connection->executeQuery($sql, ['tripId' => $trip->getId()])
            ->fetchAssociative();
    }
}
