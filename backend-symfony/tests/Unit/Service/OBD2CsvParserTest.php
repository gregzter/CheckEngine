<?php

namespace App\Tests\Unit\Service;

use App\Service\OBD2CsvParser;
use App\Service\OBD2ColumnMapper;
use App\Service\DiagnosticDetector;
use App\Service\TripDataService;
use App\Service\Diagnostic\StreamingDiagnosticAnalyzer;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

class OBD2CsvParserTest extends TestCase
{
    private OBD2CsvParser $parser;
    private OBD2ColumnMapper|MockObject $columnMapper;
    private DiagnosticDetector|MockObject $diagnosticDetector;
    private TripDataService|MockObject $tripDataService;
    private StreamingDiagnosticAnalyzer|MockObject $diagnosticAnalyzer;
    private EntityManagerInterface|MockObject $entityManager;
    private string $testCsvPath;

    protected function setUp(): void
    {
        $this->columnMapper = $this->createMock(OBD2ColumnMapper::class);
        $this->diagnosticDetector = $this->createMock(DiagnosticDetector::class);
        $this->tripDataService = $this->createMock(TripDataService::class);
        $this->diagnosticAnalyzer = $this->createMock(StreamingDiagnosticAnalyzer::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->parser = new OBD2CsvParser(
            $this->columnMapper,
            $this->diagnosticDetector,
            $this->tripDataService,
            $this->diagnosticAnalyzer,
            $this->entityManager,
            new NullLogger()
        );

        // Create a test CSV file
        $this->testCsvPath = sys_get_temp_dir() . '/test_obd2_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCsvPath)) {
            unlink($this->testCsvPath);
        }
    }

    private function createTestCsv(array $headers, array $rows): void
    {
        $handle = fopen($this->testCsvPath, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    public function testValidateCsvFileWithValidFile(): void
    {
        $headers = ['Device Time', 'Longitude', 'Latitude', 'Engine RPM(rpm)'];
        $rows = [
            ['23-oct.-2025 12:00:00.000', '-73.9857', '40.7484', '2000'],
            ['23-oct.-2025 12:00:01.000', '-73.9858', '40.7485', '2100'],
        ];
        $this->createTestCsv($headers, $rows);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'timestamp_device' => ['csv_column' => 'Device Time', 'index' => 0],
                'longitude' => ['csv_column' => 'Longitude', 'index' => 1],
                'latitude' => ['csv_column' => 'Latitude', 'index' => 2],
                'rpm' => ['csv_column' => 'Engine RPM(rpm)', 'index' => 3],
            ],
            'unmapped' => [],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertArrayHasKey('total_columns', $result);
        $this->assertArrayHasKey('recognized_columns', $result);
    }

    public function testValidateCsvFileWithMissingTimestamp(): void
    {
        $headers = ['Longitude', 'Latitude', 'Engine RPM(rpm)']; // No timestamp
        $rows = [['-73.9857', '40.7484', '2000']];
        $this->createTestCsv($headers, $rows);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'longitude' => ['csv_column' => 'Longitude', 'index' => 0],
                'latitude' => ['csv_column' => 'Latitude', 'index' => 1],
                'rpm' => ['csv_column' => 'Engine RPM(rpm)', 'index' => 2],
            ],
            'unmapped' => [],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('timestamp', strtolower($result['errors'][0]));
    }

    public function testValidateCsvFileWithNonExistentFile(): void
    {
        $result = $this->parser->validateCsvFile('/non/existent/file.csv');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('exist', strtolower($result['errors'][0]));
    }

    public function testValidateCsvFileWithEmptyFile(): void
    {
        file_put_contents($this->testCsvPath, '');

        // Empty file causes fgetcsv to return false, triggering "Invalid CSV headers"
        // We need to handle this in the mock
        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [],
            'unmapped' => [],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateCsvFileWithOnlyHeaders(): void
    {
        $headers = ['Device Time', 'Longitude', 'Latitude'];
        $this->createTestCsv($headers, []);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'timestamp_device' => ['csv_column' => 'Device Time', 'index' => 0],
                'longitude' => ['csv_column' => 'Longitude', 'index' => 1],
                'latitude' => ['csv_column' => 'Latitude', 'index' => 2],
            ],
            'unmapped' => [],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        // File with only headers should pass validation (no row count check in validateCsvFile)
        $this->assertTrue($result['valid']);
    }

    public function testValidateCsvFileDetectsUnmappedColumns(): void
    {
        $headers = ['Device Time', 'Unknown Column 1', 'Engine RPM(rpm)', 'Strange PID'];
        $rows = [['23-oct.-2025 12:00:00.000', 'value1', '2000', 'value2']];
        $this->createTestCsv($headers, $rows);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'timestamp_device' => ['csv_column' => 'Device Time', 'index' => 0],
                'rpm' => ['csv_column' => 'Engine RPM(rpm)', 'index' => 2],
            ],
            'unmapped' => ['Unknown Column 1', 'Strange PID'],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        $this->assertTrue($result['valid']);
        $this->assertEquals(2, $result['recognized_columns']);
        $this->assertEquals(4, $result['total_columns']);
    }

    public function testValidationDetectsLargeFiles(): void
    {
        // Create a CSV with many rows to test performance
        $headers = ['Device Time', 'Engine RPM(rpm)', 'Speed (OBD)(km/h)'];
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $time = date('d-M-Y H:i:s.000', strtotime("2025-10-23 12:00:00 + {$i} seconds"));
            $rows[] = [$time, (string)(2000 + $i % 100), (string)(60 + $i % 40)];
        }
        $this->createTestCsv($headers, $rows);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'timestamp_device' => ['csv_column' => 'Device Time', 'index' => 0],
                'rpm' => ['csv_column' => 'Engine RPM(rpm)', 'index' => 1],
                'speed' => ['csv_column' => 'Speed (OBD)(km/h)', 'index' => 2],
            ],
            'unmapped' => [],
        ]);

        $startTime = microtime(true);
        $result = $this->parser->validateCsvFile($this->testCsvPath);
        $duration = microtime(true) - $startTime;

        $this->assertTrue($result['valid']);
        $this->assertEquals(3, $result['recognized_columns']);
        $this->assertLessThan(0.5, $duration, "Validation should be fast (<0.5s for 1000 rows)");
    }

    public function testValidationHandlesMalformedCsvGracefully(): void
    {
        // Create a CSV with valid headers but malformed data rows
        file_put_contents($this->testCsvPath, "Device Time,RPM,Speed\n");
        file_put_contents($this->testCsvPath, "2025-10-23,2000,50\n", FILE_APPEND);
        file_put_contents($this->testCsvPath, "Invalid line with,too,many,commas,,,\n", FILE_APPEND);
        file_put_contents($this->testCsvPath, "2025-10-23,2100,55\n", FILE_APPEND);

        $this->columnMapper->method('mapCsvHeaders')->willReturn([
            'mapped' => [
                'timestamp_device' => ['csv_column' => 'Device Time', 'index' => 0],
                'rpm' => ['csv_column' => 'RPM', 'index' => 1],
            ],
            'unmapped' => [],
        ]);

        $result = $this->parser->validateCsvFile($this->testCsvPath);

        // validateCsvFile only checks headers, not data rows
        // So with valid headers + timestamp, it returns valid=true
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertTrue($result['valid']); // Headers are valid
    }
}
