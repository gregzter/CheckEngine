<?php

namespace App\Controller\Api;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\OBD2CsvParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class TripController extends AbstractController
{
    public function __construct(
        private readonly string $uploadDir,
        private readonly OBD2CsvParser $parser,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Upload CSV or ZIP file for async parsing
     *
     * @param Request $request
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/csv-upload', name: 'csv_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('csv_file');

        if (!$file) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No file uploaded',
                'message' => 'Please provide a CSV or ZIP file in the csv_file field',
            ], 400);
        }

        // Validate file
        $violations = $validator->validate($file, [
            new Assert\File(
                maxSize: '50M',
                mimeTypes: [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/vnd.ms-excel',
                    'application/zip',
                    'application/x-zip-compressed',
                ],
                mimeTypesMessage: 'Please upload a valid CSV or ZIP file'
            )
        ]);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $errors,
            ], 400);
        }

        // Detect file type
        $extension = strtolower($file->getClientOriginalExtension());
        $isZip = in_array($extension, ['zip']) ||
            in_array($file->getMimeType(), ['application/zip', 'application/x-zip-compressed']);

        // Handle ZIP file
        if ($isZip) {
            return $this->handleZipUpload($file, $request, $validator);
        }

        // Handle CSV file
        return $this->handleCsvUpload($file, $request);
    }

    /**
     * Handle CSV file upload
     */
    private function handleCsvUpload(UploadedFile $file, Request $request): JsonResponse
    {
        // Additional CSV validation (check headers)
        $handle = fopen($file->getPathname(), 'r');
        if ($handle === false) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Cannot read file',
            ], 400);
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false || count($headers) < 3) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid CSV format',
                'message' => 'CSV must have at least 3 columns with headers',
            ], 400);
        }

        // Check for timestamp column
        $hasTimestamp = $this->validateTimestampColumn($headers);

        if (!$hasTimestamp) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid CSV format',
                'message' => 'CSV must contain a timestamp column (Device Time or GPS Time)',
            ], 400);
        }

        // Get user and vehicle
        [$user, $vehicle] = $this->getUserAndVehicle($request);
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        // Store file with unique name
        $filename = uniqid('upload_', true) . '.csv';

        try {
            // Ensure upload directory exists
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0777, true);
            }

            $filepath = $this->uploadDir . '/' . $filename;
            $file->move($this->uploadDir, $filename);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'File upload failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Process CSV synchronously
        try {
            $this->logger->info("Processing uploaded CSV", [
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
            ]);

            $trip = $this->parser->parseAndStore($filepath, $user, $vehicle);

            $this->logger->info("CSV processing completed", [
                'trip_id' => $trip->getId(),
                'status' => $trip->getStatus(),
            ]);

            // Cleanup file after processing
            @unlink($filepath);

            return new JsonResponse([
                'success' => true,
                'status' => 'completed',
                'message' => 'CSV file processed successfully',
                'data' => [
                    'trip_id' => $trip->getId(),
                    'filename' => $trip->getFilename(),
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'columns' => count($headers),
                    'data_points' => $trip->getDataPointsCount(),
                    'duration' => $trip->getDuration(),
                    'status' => $trip->getStatus(),
                ],
            ], 201);
        } catch (\Exception $e) {
            // Cleanup file if processing fails
            @unlink($filepath);

            $this->logger->error("CSV processing failed", [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle ZIP file upload (extract and process CSV files)
     */
    private function handleZipUpload(UploadedFile $file, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $this->logger->info("Processing ZIP file", [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        // Store ZIP file temporarily
        $zipFilename = uniqid('upload_', true) . '.zip';
        $extractDir = $this->uploadDir . '/extract_' . uniqid();

        try {
            // Ensure upload directory exists
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0777, true);
            }

            $zipPath = $this->uploadDir . '/' . $zipFilename;
            $file->move($this->uploadDir, $zipFilename);

            // Extract ZIP
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Cannot open ZIP file');
            }

            // Create extraction directory
            mkdir($extractDir, 0777, true);
            $zip->extractTo($extractDir);
            $zip->close();

            // Find CSV files in extracted content
            $csvFiles = $this->findCsvFiles($extractDir);

            if (empty($csvFiles)) {
                throw new \RuntimeException('No CSV files found in ZIP archive');
            }

            $this->logger->info("Found CSV files in ZIP", [
                'count' => count($csvFiles),
                'files' => array_map('basename', $csvFiles),
            ]);

            // Get user and vehicle
            [$user, $vehicle] = $this->getUserAndVehicle($request);
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'User not found',
                ], 404);
            }

            // Process each CSV file
            $results = [];
            $errors = [];

            foreach ($csvFiles as $csvPath) {
                try {
                    // Validate CSV
                    $handle = fopen($csvPath, 'r');
                    if ($handle === false) {
                        throw new \RuntimeException('Cannot read CSV file: ' . basename($csvPath));
                    }

                    $headers = fgetcsv($handle);
                    fclose($handle);

                    if ($headers === false || count($headers) < 3) {
                        throw new \RuntimeException('Invalid CSV format in: ' . basename($csvPath));
                    }

                    if (!$this->validateTimestampColumn($headers)) {
                        throw new \RuntimeException('No timestamp column in: ' . basename($csvPath));
                    }

                    // Process CSV
                    $trip = $this->parser->parseAndStore($csvPath, $user, $vehicle);

                    $results[] = [
                        'filename' => basename($csvPath),
                        'trip_id' => $trip->getId(),
                        'status' => $trip->getStatus(),
                        'data_points' => $trip->getDataPointsCount(),
                        'duration' => $trip->getDuration(),
                    ];

                    $this->logger->info("CSV from ZIP processed", [
                        'filename' => basename($csvPath),
                        'trip_id' => $trip->getId(),
                    ]);
                } catch (\Exception $e) {
                    $errors[] = [
                        'filename' => basename($csvPath),
                        'error' => $e->getMessage(),
                    ];

                    $this->logger->error("CSV processing failed", [
                        'filename' => basename($csvPath),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Cleanup
            $this->recursiveRemoveDirectory($extractDir);
            @unlink($zipPath);

            $totalTrips = count($results);
            $totalErrors = count($errors);

            return new JsonResponse([
                'success' => $totalTrips > 0,
                'status' => 'completed',
                'message' => "Processed {$totalTrips} CSV file(s) from ZIP" .
                    ($totalErrors > 0 ? " ({$totalErrors} failed)" : ""),
                'data' => [
                    'original_name' => $file->getClientOriginalName(),
                    'total_files' => count($csvFiles),
                    'successful' => $totalTrips,
                    'failed' => $totalErrors,
                    'trips' => $results,
                    'errors' => $errors,
                ],
            ], $totalTrips > 0 ? 201 : 400);
        } catch (\Exception $e) {
            // Cleanup on error
            if (isset($extractDir) && is_dir($extractDir)) {
                $this->recursiveRemoveDirectory($extractDir);
            }
            if (isset($zipPath) && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            $this->logger->error("ZIP processing failed", [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'ZIP processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate that headers contain a timestamp column
     */
    private function validateTimestampColumn(array $headers): bool
    {
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            if (str_contains($normalized, 'device time') || str_contains($normalized, 'gps time')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get user and vehicle from request
     */
    private function getUserAndVehicle(Request $request): array
    {
        // Get user (for now, use demo user - in production, get from authentication)
        // TODO: Replace with actual authenticated user
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'demo@checkengine.local'])
            ?? $this->em->getRepository(User::class)->find(1);

        // Get vehicle ID from request (optional)
        $vehicleId = $request->request->get('vehicle_id')
            ? (int)$request->request->get('vehicle_id')
            : null;

        $vehicle = null;
        if ($vehicleId) {
            $vehicle = $this->em->getRepository(Vehicle::class)->find($vehicleId);
        }

        return [$user, $vehicle];
    }

    /**
     * Find all CSV files in directory (recursive)
     */
    private function findCsvFiles(string $directory): array
    {
        $csvFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['csv', 'txt'])) {
                $csvFiles[] = $file->getPathname();
            }
        }

        return $csvFiles;
    }

    /**
     * Recursively remove directory
     */
    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($directory);
    }

    /**
     * Get trip status
     *
     * @param int $id Trip ID
     * @return JsonResponse
     */
    #[Route('/trip-status/{id}', name: 'trip_status', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $trip = $this->em->getRepository(Trip::class)->find($id);

        if (!$trip) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Trip not found',
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $trip->getId(),
                'status' => $trip->getStatus(),
                'filename' => $trip->getFilename(),
                'session_date' => $trip->getSessionDate()?->format('Y-m-d H:i:s'),
                'duration' => $trip->getDuration(),
                'data_points' => $trip->getDataPointsCount(),
                'analysis_results' => $trip->getAnalysisResults(),
                'catalyst_efficiency' => $trip->getCatalystEfficiency(),
                'avg_fuel_trim_st' => $trip->getAvgFuelTrimST(),
                'avg_fuel_trim_lt' => $trip->getAvgFuelTrimLT(),
            ],
        ]);
    }
}
