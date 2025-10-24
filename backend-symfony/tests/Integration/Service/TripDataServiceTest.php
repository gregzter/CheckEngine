<?php

namespace App\Tests\Integration\Service;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleModel;
use App\Service\TripDataService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TripDataServiceTest extends KernelTestCase
{
    private TripDataService $tripDataService;
    private Connection $connection;
    private ?Trip $testTrip = null;
    private ?User $testUser = null;
    private ?Vehicle $testVehicle = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->tripDataService = $container->get(TripDataService::class);
        $this->connection = $container->get(Connection::class);

        // Create test data
        $this->createTestFixtures();
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        if ($this->testTrip) {
            $this->connection->executeStatement(
                'DELETE FROM trip_data WHERE trip_id = ?',
                [$this->testTrip->getId()]
            );
            $this->connection->executeStatement(
                'DELETE FROM trip WHERE id = ?',
                [$this->testTrip->getId()]
            );
        }

        if ($this->testVehicle) {
            $this->connection->executeStatement(
                'DELETE FROM vehicle WHERE id = ?',
                [$this->testVehicle->getId()]
            );
        }

        if ($this->testUser) {
            $this->connection->executeStatement(
                'DELETE FROM "user" WHERE id = ?',
                [$this->testUser->getId()]
            );
        }

        parent::tearDown();
    }

    private function createTestFixtures(): void
    {
        // Create test user
        $this->connection->insert('"user"', [
            'email' => 'test_' . uniqid() . '@phpunit.local',
            'password' => 'hashed_password',
            'roles' => json_encode(['ROLE_USER']),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $userId = (int) $this->connection->lastInsertId();

        $this->testUser = new User();
        $reflection = new \ReflectionClass($this->testUser);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->testUser, $userId);

        // Create vehicle model
        $this->connection->insert('vehicle_model', [
            'manufacturer' => 'Test Make',
            'model' => 'Test Model',
            'year_start' => 2020,
            'year_end' => 2024,
            'generation' => 'Test Gen',
            'engine_code' => 'TEST123',
            'displacement' => '2.0',
            'fuel_type' => 'Gasoline',
            'horse_power' => 150,
            'is_hybrid' => 0, // Use 0 instead of false for PostgreSQL boolean
        ]);
        $modelId = (int) $this->connection->lastInsertId();

        // Create test vehicle
        $this->connection->insert('vehicle', [
            'owner_id' => $userId,
            'model_id' => $modelId,
            'nickname' => 'Test Vehicle',
            'vin' => 'TEST' . uniqid(),
            'year' => 2020,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $vehicleId = (int) $this->connection->lastInsertId();

        $this->testVehicle = new Vehicle();
        $reflection = new \ReflectionClass($this->testVehicle);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->testVehicle, $vehicleId);

        // Create test trip
        $sessionDate = new \DateTimeImmutable('2025-01-15 10:00:00');
        $this->connection->insert('trip', [
            'vehicle_id' => $vehicleId,
            'filename' => 'test-trip.csv',
            'session_date' => $sessionDate->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'uploaded_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $tripId = (int) $this->connection->lastInsertId();

        $this->testTrip = new Trip();
        $reflection = new \ReflectionClass($this->testTrip);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->testTrip, $tripId);

        // Use reflection to set sessionDate
        $sessionDateProperty = $reflection->getProperty('sessionDate');
        $sessionDateProperty->setAccessible(true);
        $sessionDateProperty->setValue($this->testTrip, $sessionDate);
    }

    public function testBulkInsertSmallBatch(): void
    {
        $dataPoints = [];
        $baseTime = $this->testTrip->getSessionDate();

        // Generate 100 test data points
        for ($i = 0; $i < 100; $i++) {
            $dataPoints[] = [
                'timestamp' => $baseTime->modify("+{$i} seconds"),
                'pid_name' => 'rpm',
                'value' => 2000 + ($i % 50) * 10,
                'unit' => 'rpm',
            ];
        }

        $insertedCount = $this->tripDataService->bulkInsert($this->testTrip, $dataPoints);

        $this->assertEquals(100, $insertedCount);

        // Verify data was inserted
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM trip_data WHERE trip_id = ?',
            [$this->testTrip->getId()]
        );
        $this->assertEquals(100, $count);
    }

    public function testBulkInsertLargeBatch(): void
    {
        $dataPoints = [];
        $baseTime = $this->testTrip->getSessionDate();

        // Generate 5,000 test data points
        for ($i = 0; $i < 5000; $i++) {
            $dataPoints[] = [
                'timestamp' => $baseTime->modify("+{$i} seconds"),
                'pid_name' => 'rpm',
                'value' => 2000 + ($i % 100) * 10,
                'unit' => 'rpm',
            ];
        }

        $startMemory = memory_get_usage();
        $startTime = microtime(true);

        $insertedCount = $this->tripDataService->bulkInsert($this->testTrip, $dataPoints);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $duration = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB

        $this->assertEquals(5000, $insertedCount);
        $this->assertLessThan(5, $duration, "Bulk insert took too long: {$duration}s");
        $this->assertLessThan(50, $memoryUsed, "Memory usage too high: {$memoryUsed} MB");

        // Verify data
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM trip_data WHERE trip_id = ?',
            [$this->testTrip->getId()]
        );
        $this->assertEquals(5000, $count);
    }

    public function testBulkInsertWithPartialData(): void
    {
        $dataPoints = [
            [
                'timestamp' => $this->testTrip->getSessionDate(),
                'pid_name' => 'rpm',
                'value' => 2000,
                'unit' => 'rpm',
            ],
            [
                'timestamp' => $this->testTrip->getSessionDate()->modify('+1 second'),
                'pid_name' => 'speed',
                'value' => 60,
                'unit' => 'km/h',
            ],
            [
                'timestamp' => $this->testTrip->getSessionDate()->modify('+2 seconds'),
                'pid_name' => 'throttle_position',
                'value' => 40,
                'unit' => '%',
            ],
        ];

        $insertedCount = $this->tripDataService->bulkInsert($this->testTrip, $dataPoints);

        $this->assertEquals(3, $insertedCount);

        // Verify data was inserted
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM trip_data WHERE trip_id = ?',
            [$this->testTrip->getId()]
        );

        $this->assertEquals(3, $count);
    }

    public function testBulkInsertEmptyArray(): void
    {
        $insertedCount = $this->tripDataService->bulkInsert($this->testTrip, []);

        $this->assertEquals(0, $insertedCount);
    }

    public function testBulkInsertWithDiagnosticData(): void
    {
        $dataPoints = [];
        $baseTime = $this->testTrip->getSessionDate();

        // Test with various diagnostic PIDs
        $pids = [
            ['name' => 'o2_b1s1_voltage', 'unit' => 'V'],
            ['name' => 'stft_b1', 'unit' => '%'],
            ['name' => 'ltft_b1', 'unit' => '%'],
            ['name' => 'coolant_temp', 'unit' => '°C'],
        ];

        for ($i = 0; $i < 50; $i++) {
            foreach ($pids as $pid) {
                $dataPoints[] = [
                    'timestamp' => $baseTime->modify("+{$i} seconds"),
                    'pid_name' => $pid['name'],
                    'value' => 50.0 + $i,
                    'unit' => $pid['unit'],
                ];
            }
        }

        $insertedCount = $this->tripDataService->bulkInsert($this->testTrip, $dataPoints);

        $this->assertEquals(200, $insertedCount); // 50 iterations * 4 PIDs

        // Verify data was inserted
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM trip_data WHERE trip_id = ?',
            [$this->testTrip->getId()]
        );

        $this->assertEquals(200, $count);
    }
}
