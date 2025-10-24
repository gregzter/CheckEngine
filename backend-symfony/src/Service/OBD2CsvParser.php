<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Parse OBD2 CSV files and store data in database
 * Uses streaming approach to handle large files efficiently
 */
class OBD2CsvParser
{
    private const CHUNK_SIZE = 1000; // Rows per bulk insert batch
    private const DATE_FORMAT_DEVICE = 'd-M.-Y H:i:s.u'; // 23-oct.-2025 12:00:11.832
    private const DATE_FORMAT_GPS = 'D M d H:i:s e Y';    // Thu Oct 23 12:00:11 GMT+02:00 2025

    public function __construct(
        private readonly OBD2ColumnMapper $columnMapper,
        private readonly DiagnosticDetector $diagnosticDetector,
        private readonly TripDataService $tripDataService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Parse CSV file and create Trip with all data
     *
     * @param string $filepath Full path to CSV file
     * @param User $user Owner of the trip
     * @param Vehicle|null $vehicle Vehicle (optional, can be null)
     * @return Trip Created trip with all data loaded
     * @throws \RuntimeException If file cannot be parsed
     */
    public function parseAndStore(string $filepath, User $user, ?Vehicle $vehicle = null): Trip
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: $filepath");
        }

        $this->logger->info("Starting CSV parsing", ['file' => $filepath]);

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: $filepath");
        }

        try {
            // 1. Read and normalize headers
            $rawHeaders = fgetcsv($handle);
            if ($rawHeaders === false) {
                throw new \RuntimeException("Cannot read CSV headers");
            }

            $mapping = $this->columnMapper->mapCsvHeaders($rawHeaders);
            $this->logger->info("Column mapping complete", [
                'total_columns' => count($rawHeaders),
                'recognized_columns' => count($mapping['mapped'] ?? []),
                'unknown_columns' => count($mapping['unmapped'] ?? [])
            ]);

            // 2. Create Trip entity
            $trip = new Trip();
            // Trip is linked to Vehicle (which has User), not directly to User
            if ($vehicle) {
                $trip->setVehicle($vehicle);
            }
            $trip->setFilename(basename($filepath));
            $trip->setFilePath($filepath);
            $trip->setStatus('processing');
            $trip->setSessionDate(new \DateTimeImmutable()); // Set session date to now

            // We'll update these after parsing
            $firstTimestamp = null;
            $lastTimestamp = null;

            $this->em->persist($trip);
            $this->em->flush(); // Get trip ID for inserting data

            // 3. Stream CSV data in chunks
            $totalRows = 0;
            $dataPoints = [];
            $rawDataForDiagnostics = [];

            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;

                // Parse row with column mapping
                $parsedRow = $this->parseRow($row, $rawHeaders, $mapping);

                if ($parsedRow === null) {
                    continue; // Skip invalid rows
                }

                // Extract timestamp
                $timestamp = $this->extractTimestamp($parsedRow);
                if ($timestamp === null) {
                    // Debug first failed timestamp
                    if ($totalRows === 1) {
                        $this->logger->error("First row timestamp extraction failed", [
                            'parsedRow' => $parsedRow,
                            'timestamp_device' => $parsedRow['timestamp_device'] ?? 'N/A',
                            'timestamp_gps' => $parsedRow['timestamp_gps'] ?? 'N/A'
                        ]);
                    }
                    $this->logger->warning("Row without timestamp", ['row' => $totalRows]);
                    continue;
                }

                if ($firstTimestamp === null) {
                    $firstTimestamp = $timestamp;
                }
                $lastTimestamp = $timestamp;

                // Build data points for bulk insert
                foreach ($parsedRow as $pidName => $value) {
                    if ($pidName === 'timestamp_device' || $pidName === 'timestamp_gps') {
                        continue; // Skip timestamps
                    }

                    $dataPoints[] = [
                        'timestamp' => $timestamp,
                        'pid_name' => $pidName,
                        'value' => $value,
                        'unit' => null, // Can be enhanced later from OBD2Column entity
                    ];
                }

                // Collect raw data for diagnostics (keep in memory for analysis)
                // TODO: Optimize memory usage - currently disabled for large files
                // $rawDataForDiagnostics[] = $parsedRow;

                // Flush batch to database
                if (count($dataPoints) >= self::CHUNK_SIZE) {
                    try {
                        $this->tripDataService->bulkInsert($trip, $dataPoints);
                        $dataPoints = [];
                    } catch (\Exception $e) {
                        $this->logger->error("Bulk insert failed", [
                            'error' => $e->getMessage(),
                            'trip_id' => $trip->getId(),
                            'chunk_size' => count($dataPoints)
                        ]);
                        throw $e;
                    }
                }
            }

            // 4. Insert remaining data points
            if (!empty($dataPoints)) {
                $this->tripDataService->bulkInsert($trip, $dataPoints);
            }

            fclose($handle);

            // 5. Update Trip metadata
            if ($firstTimestamp && $lastTimestamp) {
                $trip->setSessionDate($firstTimestamp);
                $duration = $lastTimestamp->getTimestamp() - $firstTimestamp->getTimestamp();
                $trip->setDuration($duration);
            }

            $trip->setDataPointsCount($totalRows);
            $trip->setStatus('parsed');

            // 6. Run diagnostics
            $this->logger->info("Running diagnostics", ['trip_id' => $trip->getId()]);
            $diagnosticResults = $this->runDiagnostics($rawDataForDiagnostics, $mapping);

            // 7. Store diagnostic results
            $trip->setAnalysisResults($diagnosticResults);
            $trip->setStatus('analyzed');

            if (isset($diagnosticResults['catalyst_efficiency'])) {
                $trip->setCatalystEfficiency((string)$diagnosticResults['catalyst_efficiency']);
            }
            if (isset($diagnosticResults['fuel_trim']['short_term_avg'])) {
                $trip->setAvgFuelTrimST((string)$diagnosticResults['fuel_trim']['short_term_avg']);
            }
            if (isset($diagnosticResults['fuel_trim']['long_term_avg'])) {
                $trip->setAvgFuelTrimLT((string)$diagnosticResults['fuel_trim']['long_term_avg']);
            }

            $this->em->flush();

            $this->logger->info("CSV parsing complete", [
                'trip_id' => $trip->getId(),
                'total_rows' => $totalRows,
                'duration_seconds' => $duration ?? 0
            ]);

            return $trip;
        } catch (\Exception $e) {
            $this->logger->error("CSV parsing failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (isset($trip) && $this->em->isOpen()) {
                try {
                    $trip->setStatus('error');
                    $this->em->flush();
                } catch (\Exception $flushException) {
                    $this->logger->error("Failed to update trip status", [
                        'error' => $flushException->getMessage()
                    ]);
                }
            }
            
            throw $e;
        }
    }

    /**
     * Parse a single CSV row with column mapping
     */
    private function parseRow(array $row, array $headers, array $mapping): ?array
    {
        $parsed = [];
        $mappedColumns = $mapping['mapped'] ?? [];

        foreach ($row as $index => $value) {
            $originalColumn = trim($headers[$index] ?? '');
            if ($originalColumn === '') {
                continue;
            }

            // Find the normalized column name for this original column
            $normalizedColumn = null;
            foreach ($mappedColumns as $normalized => $info) {
                if ($info['csv_column'] === $originalColumn) {
                    $normalizedColumn = $normalized;
                    break;
                }
            }

            if ($normalizedColumn === null) {
                continue; // Skip unknown columns
            }

            // For timestamps, keep as string (don't clean)
            if ($normalizedColumn === 'timestamp_device' || $normalizedColumn === 'timestamp_gps') {
                $parsed[$normalizedColumn] = trim($value);
            } else {
                // Clean value (remove '-', 'N/A', etc.) for numeric columns
                $cleanValue = $this->cleanValue($value);
                $parsed[$normalizedColumn] = $cleanValue;
            }
        }

        return empty($parsed) ? null : $parsed;
    }

    /**
     * Extract timestamp from parsed row (prefer device_time, fallback to gps_time)
     */
    private function extractTimestamp(array $parsedRow): ?\DateTimeImmutable
    {
        // Try timestamp_device first (Device Time column)
        if (isset($parsedRow['timestamp_device']) && !empty($parsedRow['timestamp_device'])) {
            try {
                $date = \DateTimeImmutable::createFromFormat(
                    self::DATE_FORMAT_DEVICE,
                    $parsedRow['timestamp_device']
                );
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception $e) {
                // Fallback to simple parsing
            }
            
            try {
                return new \DateTimeImmutable($parsedRow['timestamp_device']);
            } catch (\Exception $e2) {
                // Continue to GPS time
            }
        }

        // Fallback to timestamp_gps (GPS Time column)
        if (isset($parsedRow['timestamp_gps']) && !empty($parsedRow['timestamp_gps'])) {
            try {
                $date = \DateTimeImmutable::createFromFormat(
                    self::DATE_FORMAT_GPS,
                    $parsedRow['timestamp_gps']
                );
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception $e) {
                // Fallback
            }
            
            try {
                return new \DateTimeImmutable($parsedRow['timestamp_gps']);
            } catch (\Exception $e2) {
                return null;
            }
        }

        return null;
    }

    /**
     * Clean CSV value (remove invalid markers)
     */
    private function cleanValue(string $value): ?float
    {
        $value = trim($value);

        // Empty or invalid markers
        if ($value === '' || $value === '-' || strtoupper($value) === 'N/A') {
            return null;
        }

        // Convert to float
        $floatValue = (float)$value;

        // Check for error values (51199 for Prius, etc.)
        if ($floatValue >= 51199) {
            return null;
        }

        return $floatValue;
    }

    /**
     * Run diagnostics on parsed data
     */
    private function runDiagnostics(array $rawData, array $mapping): array
    {
        // Convert to format expected by DiagnosticDetector
        // (array of rows with normalized column names)

        $diagnosticResults = [];

        // Check what diagnostics are available
        $availableColumns = array_keys($rawData[0] ?? []);

        // For now, just return available columns
        // TODO: Enhance DiagnosticDetector to have getAvailableDiagnostics method
        $diagnosticResults['available_columns'] = $availableColumns;

        // TODO: Implement diagnostic calculations using DiagnosticDetector
        // This would call methods like:
        // - $this->diagnosticDetector->analyzeCatalyst($rawData)
        // - $this->diagnosticDetector->analyzeO2Sensors($rawData)
        // - $this->diagnosticDetector->analyzeFuelTrims($rawData)

        return $diagnosticResults;
    }

    /**
     * Validate CSV file before parsing
     */
    public function validateCsvFile(string $filepath): array
    {
        $errors = [];

        if (!file_exists($filepath)) {
            $errors[] = "File does not exist";
            return ['valid' => false, 'errors' => $errors];
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            $errors[] = "Cannot open file";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check headers
        $headers = fgetcsv($handle);
        if ($headers === false || count($headers) < 3) {
            $errors[] = "Invalid CSV headers (minimum 3 columns required)";
        }

        // Check for timestamp column
        $mapping = $this->columnMapper->mapCsvHeaders($headers);
        $mappedColumns = $mapping['mapped'] ?? [];
        $hasTimestamp = isset($mappedColumns['timestamp_device']) || isset($mappedColumns['timestamp_gps']);

        if (!$hasTimestamp) {
            $errors[] = "No timestamp column found (timestamp_device or timestamp_gps required)";
        }

        fclose($handle);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_columns' => count($headers),
            'recognized_columns' => count($mappedColumns),
        ];
    }
}
