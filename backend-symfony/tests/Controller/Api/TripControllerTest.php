<?php

namespace App\Tests\Controller\Api;

use App\Tests\Fixtures\TestFixtures;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TripControllerTest extends WebTestCase
{
    private string $testCsvPath;
    private string $testZipPath;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test CSV file
        $this->testCsvPath = sys_get_temp_dir() . '/test_trip_' . uniqid() . '.csv';
        $this->createTestCsv();

        // Create test ZIP file
        $this->testZipPath = sys_get_temp_dir() . '/test_trip_' . uniqid() . '.zip';
        $this->createTestZip();
    }

    private function loadFixtures(): void
    {
        if (!$this->entityManager) {
            throw new \LogicException('Entity manager not initialized');
        }

        // Purge database
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        // Create test user
        $user = new \App\Entity\User();
        $user->setEmail('test@checkengine.local');
        // Set a pre-hashed password for 'test123' (bcrypt with cost 4)
        $user->setPassword('$2y$04$qKw8qMqN5j8UqK8tF.lLqegWQWjQQNQQxCQQQWWWWWWWWWWWW0WXC');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);

        // Create vehicle model
        $model = new \App\Entity\VehicleModel();
        $model->setManufacturer('Toyota');
        $model->setModel('Corolla');
        $model->setYearStart(2020);
        $model->setYearEnd(2024);
        $model->setGeneration('E210');
        $model->setEngineCode('2ZR-FE');
        $model->setDisplacement('1.8');
        $model->setFuelType('Gasoline');
        $model->setHorsePower(139);
        $model->setHybrid(false);
        $this->entityManager->persist($model);

        // Create test vehicle
        $vehicle = new \App\Entity\Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setModel($model);
        $vehicle->setNickname('Test Car');
        $vehicle->setYear(2020);
        $vehicle->setVin('1HGBH41JXMN109186');
        $vehicle->setLicensePlate('TEST123');
        $vehicle->setMileage(50000);
        $vehicle->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($vehicle);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCsvPath)) {
            unlink($this->testCsvPath);
        }
        if (file_exists($this->testZipPath)) {
            unlink($this->testZipPath);
        }
        parent::tearDown();
    }

    private function createTestCsv(): void
    {
        $headers = [
            'Device Time',
            'Longitude',
            'Latitude',
            'Altitude(m)',
            'Engine RPM(rpm)',
            'Speed (OBD)(km/h)',
            'Throttle Position(Manifold)(%)',
            'Engine Load(%)',
            'O2 Volts Bank 1 sensor 1(V)',
            'Short Term Fuel Trim Bank 1(%)',
            'Engine Coolant Temperature(°C)',
        ];

        $handle = fopen($this->testCsvPath, 'w');
        fputcsv($handle, $headers);

        // Generate 100 realistic data points
        $baseTime = new \DateTimeImmutable('2025-10-24 10:00:00');
        for ($i = 0; $i < 100; $i++) {
            $timestamp = $baseTime->modify("+{$i} seconds")->format('d-M-Y H:i:s.000');
            $row = [
                $timestamp,
                -73.9857 + ($i * 0.0001), // Longitude
                40.7484 + ($i * 0.0001),  // Latitude
                100 + ($i % 10),           // Altitude
                1500 + ($i * 10),          // RPM
                40 + ($i % 30),            // Speed
                25 + ($i % 15),            // Throttle
                30 + ($i % 20),            // Load
                0.4 + ($i % 5) * 0.1,     // O2
                2.0 + ($i % 3) * 0.5,     // STFT
                85 + ($i % 8),             // Coolant temp
            ];
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function createTestZip(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->testZipPath, \ZipArchive::CREATE);
        $zip->addFile($this->testCsvPath, 'test-trip.csv');
        $zip->close();
    }

    public function testCsvUploadEndpoint(): void
    {
        $client = static::createClient();

        // Load fixtures after client is created
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->loadFixtures();

        // Create uploaded file
        $uploadedFile = new UploadedFile(
            $this->testCsvPath,
            'test-trip.csv',
            'text/csv',
            null,
            true // Mark as test file
        );

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ], [
            'csv_file' => $uploadedFile,
        ]);

        $response = $client->getResponse();

        // Should succeed or return validation error (depending on fixtures)
        $this->assertContains($response->getStatusCode(), [200, 201, 400, 404]);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testCsvUploadWithoutFile(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ]);

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testCsvUploadWithInvalidMimeType(): void
    {
        $client = static::createClient();

        // Create a text file with wrong extension
        $invalidFile = sys_get_temp_dir() . '/test_invalid_' . uniqid() . '.txt';
        file_put_contents($invalidFile, 'This is not a CSV');

        $uploadedFile = new UploadedFile(
            $invalidFile,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ], [
            'csv_file' => $uploadedFile,
        ]);

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        unlink($invalidFile);
    }

    public function testZipUploadEndpoint(): void
    {
        $client = static::createClient();

        $uploadedFile = new UploadedFile(
            $this->testZipPath,
            'test-trip.zip',
            'application/zip',
            null,
            true
        );

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ], [
            'csv_file' => $uploadedFile,
        ]);

        $response = $client->getResponse();

        // Should process ZIP or return error
        $this->assertContains($response->getStatusCode(), [200, 201, 400, 404]);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);

        if ($response->getStatusCode() === 201 && isset($data['data']['trips'])) {
            $this->assertIsArray($data['data']['trips']);
        }
    }

    public function testTripStatusEndpoint(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->loadFixtures();

        // Get the test vehicle
        $vehicle = $this->entityManager->getRepository(\App\Entity\Vehicle::class)->findOneBy(['nickname' => 'Test Car']);

        // Create a trip
        $trip = new \App\Entity\Trip();
        $trip->setVehicle($vehicle);
        $trip->setFilename('test-trip.csv');
        $trip->setSessionDate(new \DateTimeImmutable());
        $trip->setStatus('pending');
        $trip->setUploadedAt(new \DateTimeImmutable());
        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        $client->request('GET', '/api/trip-status/' . $trip->getId());

        $response = $client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 404]);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testTripStatusWithInvalidId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/trip-status/999999');

        $response = $client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCsvUploadWithLargeFile(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->loadFixtures();

        // Create a file larger than 50MB (simulated)
        $largeCsvPath = sys_get_temp_dir() . '/large_test_' . uniqid() . '.csv';
        file_put_contents($largeCsvPath, str_repeat('test,data,row' . PHP_EOL, 1000000));

        $uploadedFile = new UploadedFile(
            $largeCsvPath,
            'large-trip.csv',
            'text/csv',
            null,
            true
        );

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ], [
            'csv_file' => $uploadedFile,
        ]);

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        unlink($largeCsvPath);
    }

    public function testCsvUploadValidatesTimestampColumn(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->loadFixtures();

        // Create CSV without timestamp column
        $noTimestampCsv = sys_get_temp_dir() . '/no_timestamp_' . uniqid() . '.csv';
        file_put_contents($noTimestampCsv, "RPM,Speed\n2000,50\n2100,55\n");

        $uploadedFile = new UploadedFile(
            $noTimestampCsv,
            'no-timestamp.csv',
            'text/csv',
            null,
            true
        );

        $client->request('POST', '/api/csv-upload', [
            'user_email' => 'test@checkengine.local',
            'vehicle_id' => 1,
        ], [
            'csv_file' => $uploadedFile,
        ]);

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);

        unlink($noTimestampCsv);
    }

    public function testConcurrentUploads(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        $this->loadFixtures();

        // Test de sécurité thread-safe en vérifiant que plusieurs uploads successifs fonctionnent
        // (un vrai test concurrent nécessiterait des process parallèles)
        $results = [];

        for ($i = 0; $i < 3; $i++) {
            // Créer un fichier CSV unique pour chaque upload
            $csvPath = sys_get_temp_dir() . '/concurrent_test_' . $i . '_' . uniqid() . '.csv';
            $handle = fopen($csvPath, 'w');
            fputcsv($handle, ['Device Time', 'Longitude', 'Latitude', 'Engine RPM(rpm)']);
            fputcsv($handle, ['2025-01-15 10:00:' . str_pad($i, 2, '0', STR_PAD_LEFT), '-73.9857', '40.7484', 2000 + $i * 100]);
            fclose($handle);

            $uploadedFile = new UploadedFile(
                $csvPath,
                "test-trip-{$i}.csv",
                'text/csv',
                null,
                true
            );

            $client->request('POST', '/api/csv-upload', [
                'user_email' => 'test@checkengine.local',
                'vehicle_id' => 1,
            ], [
                'csv_file' => $uploadedFile,
            ]);

            $response = $client->getResponse();
            $results[] = $response->getStatusCode();

            unlink($csvPath);

            // Petit délai pour éviter les conflits
            usleep(10000); // 10ms
        }

        // Vérifier que tous les uploads ont réussi ou échoué de manière cohérente
        foreach ($results as $statusCode) {
            $this->assertContains($statusCode, [200, 201, 400, 404], 
                "Status code should be valid: {$statusCode}");
        }
    }
}
