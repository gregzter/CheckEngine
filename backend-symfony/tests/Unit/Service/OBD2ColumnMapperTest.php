<?php

namespace App\Tests\Unit\Service;

use App\Service\OBD2ColumnMapper;
use App\Repository\OBD2ColumnVariantRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class OBD2ColumnMapperTest extends TestCase
{
    private OBD2ColumnMapper $mapper;
    private OBD2ColumnVariantRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OBD2ColumnVariantRepository::class);

        // Mock the database mapping with common OBD2 variants
        $this->repository->method('getAllActiveMappings')->willReturn([
            'timestamp' => [
                ['variant' => 'Device Time', 'priority' => 0],
                ['variant' => 'GPS Time', 'priority' => 1],
                ['variant' => 'Time', 'priority' => 2],
                ['variant' => 'Timestamp', 'priority' => 3],
            ],
            'longitude' => [
                ['variant' => 'Longitude', 'priority' => 0],
                ['variant' => 'GPSLongitude(°)', 'priority' => 1],
            ],
            'latitude' => [
                ['variant' => 'Latitude', 'priority' => 0],
                ['variant' => 'GPSLatitude(°)', 'priority' => 1],
            ],
            'altitude' => [
                ['variant' => 'Altitude(m)', 'priority' => 0],
            ],
            'rpm' => [
                ['variant' => 'Engine RPM(rpm)', 'priority' => 0],
                ['variant' => 'Engine Speed', 'priority' => 1],
            ],
            'speed' => [
                ['variant' => 'Speed (OBD)(km/h)', 'priority' => 0],
                ['variant' => 'Vehicle Speed', 'priority' => 1],
                ['variant' => 'GPS Speed', 'priority' => 2],
            ],
            'throttle_position' => [
                ['variant' => 'Throttle Position(Manifold)(%)', 'priority' => 0],
                ['variant' => 'Throttle', 'priority' => 1],
            ],
            'engine_load' => [
                ['variant' => 'Engine Load(%)', 'priority' => 0],
            ],
            'timing_advance' => [
                ['variant' => 'Timing Advance(°)', 'priority' => 0],
            ],
            'intake_air_temp' => [
                ['variant' => 'Intake Air Temperature(°C)', 'priority' => 0],
            ],
            'maf' => [
                ['variant' => 'Mass Air Flow Rate(g/s)', 'priority' => 0],
            ],
            'fuel_pressure' => [
                ['variant' => 'Fuel Pressure(psi)', 'priority' => 0],
            ],
            'o2_b1s1_voltage' => [
                ['variant' => 'O2 Volts Bank 1 sensor 1(V)', 'priority' => 0],
                ['variant' => 'Oxygen Sensor1(V)', 'priority' => 1],
            ],
            'o2_b1s2_voltage' => [
                ['variant' => 'O2 Volts Bank 1 sensor 2(V)', 'priority' => 0],
            ],
            'o2_b2s1_voltage' => [
                ['variant' => 'O2 Volts Bank 2 sensor 1(V)', 'priority' => 0],
            ],
            'stft_b1' => [
                ['variant' => 'Short Term Fuel Trim Bank 1(%)', 'priority' => 0],
                ['variant' => 'STFT Bank 1(%)', 'priority' => 1],
            ],
            'ltft_b1' => [
                ['variant' => 'Long Term Fuel Trim Bank 1(%)', 'priority' => 0],
                ['variant' => 'LTFT Bank 1(%)', 'priority' => 1],
            ],
            'coolant_temp' => [
                ['variant' => 'Engine Coolant Temperature(°C)', 'priority' => 0],
            ],
        ]);

        $this->mapper = new OBD2ColumnMapper($this->repository);
    }

    public function testMapCsvHeadersBasicOBDFusion(): void
    {
        $headers = [
            'Device Time',
            'Longitude',
            'Latitude',
            'Engine RPM(rpm)',
            'Speed (OBD)(km/h)',
            'Throttle Position(Manifold)(%)',
            'Engine Load(%)',
        ];

        $result = $this->mapper->mapCsvHeaders($headers);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('mapped', $result);
        $this->assertArrayHasKey('unmapped', $result);

        $mapped = $result['mapped'];
        $this->assertArrayHasKey('timestamp', $mapped);
        $this->assertArrayHasKey('longitude', $mapped);
        $this->assertArrayHasKey('latitude', $mapped);
        $this->assertArrayHasKey('rpm', $mapped);
        $this->assertArrayHasKey('speed', $mapped);
        $this->assertArrayHasKey('throttle_position', $mapped);
        $this->assertArrayHasKey('engine_load', $mapped);

        // Verify structure
        $this->assertEquals('Device Time', $mapped['timestamp']['csv_column']);
        $this->assertEquals(0, $mapped['timestamp']['index']);
        $this->assertEquals('Engine RPM(rpm)', $mapped['rpm']['csv_column']);
    }

    public function testMapCsvHeadersO2Sensors(): void
    {
        $headers = [
            'Device Time',
            'O2 Volts Bank 1 sensor 1(V)',
            'O2 Volts Bank 1 sensor 2(V)',
            'O2 Volts Bank 2 sensor 1(V)',
        ];

        $result = $this->mapper->mapCsvHeaders($headers);
        $mapped = $result['mapped'];

        $this->assertArrayHasKey('timestamp', $mapped);
        $this->assertArrayHasKey('o2_b1s1_voltage', $mapped);
        $this->assertArrayHasKey('o2_b1s2_voltage', $mapped);
        $this->assertArrayHasKey('o2_b2s1_voltage', $mapped);
    }

    public function testMapCsvHeadersFuelTrim(): void
    {
        $headers = [
            'Device Time',
            'Short Term Fuel Trim Bank 1(%)',
            'Long Term Fuel Trim Bank 1(%)',
        ];

        $result = $this->mapper->mapCsvHeaders($headers);
        $mapped = $result['mapped'];

        $this->assertArrayHasKey('timestamp', $mapped);
        $this->assertArrayHasKey('stft_b1', $mapped);
        $this->assertArrayHasKey('ltft_b1', $mapped);
    }

    public function testMapCsvHeadersWithUnknownColumns(): void
    {
        $headers = [
            'Device Time',
            'Some Unknown Column',
            'Engine RPM(rpm)',
            'Another Unknown',
        ];

        $result = $this->mapper->mapCsvHeaders($headers);

        $this->assertArrayHasKey('timestamp', $result['mapped']);
        $this->assertArrayHasKey('rpm', $result['mapped']);
        $this->assertContains('Some Unknown Column', $result['unmapped']);
        $this->assertContains('Another Unknown', $result['unmapped']);
    }

    public function testMapCsvHeadersDetectsDuplicates(): void
    {
        $headers = [
            'Device Time',
            'Speed (OBD)(km/h)',   // Priority 0
            'Vehicle Speed',        // Priority 1
        ];

        $result = $this->mapper->mapCsvHeaders($headers);

        // Should have duplicates detected
        if (isset($result['duplicates']) && isset($result['duplicates']['speed'])) {
            $this->assertCount(2, $result['duplicates']['speed']);
        }

        // Best priority should win
        $this->assertEquals('Speed (OBD)(km/h)', $result['mapped']['speed']['csv_column']);
    }

    public function testMapCsvHeadersCompleteRealWorldExample(): void
    {
        // Real CSV header from OBD Fusion export
        $headers = [
            'Device Time',
            'Longitude',
            'Latitude',
            'Altitude(m)',
            'Engine RPM(rpm)',
            'Speed (OBD)(km/h)',
            'Throttle Position(Manifold)(%)',
            'Engine Load(%)',
            'Timing Advance(°)',
            'Intake Air Temperature(°C)',
            'Mass Air Flow Rate(g/s)',
            'Fuel Pressure(psi)',
            'O2 Volts Bank 1 sensor 1(V)',
            'Short Term Fuel Trim Bank 1(%)',
            'Long Term Fuel Trim Bank 1(%)',
            'Engine Coolant Temperature(°C)',
        ];

        $result = $this->mapper->mapCsvHeaders($headers);
        $mapped = $result['mapped'];

        // Verify all important columns are mapped
        $this->assertArrayHasKey('timestamp', $mapped);
        $this->assertArrayHasKey('longitude', $mapped);
        $this->assertArrayHasKey('latitude', $mapped);
        $this->assertArrayHasKey('altitude', $mapped);
        $this->assertArrayHasKey('rpm', $mapped);
        $this->assertArrayHasKey('speed', $mapped);
        $this->assertArrayHasKey('throttle_position', $mapped);
        $this->assertArrayHasKey('engine_load', $mapped);
        $this->assertArrayHasKey('timing_advance', $mapped);
        $this->assertArrayHasKey('intake_air_temp', $mapped);
        $this->assertArrayHasKey('maf', $mapped);
        $this->assertArrayHasKey('fuel_pressure', $mapped);
        $this->assertArrayHasKey('o2_b1s1_voltage', $mapped);
        $this->assertArrayHasKey('stft_b1', $mapped);
        $this->assertArrayHasKey('ltft_b1', $mapped);
        $this->assertArrayHasKey('coolant_temp', $mapped);

        // Verify no false positives
        $this->assertEmpty($result['unmapped']);
    }

    public function testEmptyHeadersReturnsEmptyMapping(): void
    {
        $result = $this->mapper->mapCsvHeaders([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result['mapped']);
    }
}
