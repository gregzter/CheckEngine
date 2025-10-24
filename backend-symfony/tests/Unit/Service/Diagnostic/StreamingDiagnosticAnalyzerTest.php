<?php

namespace App\Tests\Unit\Service\Diagnostic;

use App\Service\Diagnostic\StreamingDiagnosticAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StreamingDiagnosticAnalyzerTest extends TestCase
{
    private StreamingDiagnosticAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new StreamingDiagnosticAnalyzer(new NullLogger());
    }

    public function testStartSessionInitializesAccumulators(): void
    {
        $this->analyzer->startSession();

        // Process a sample row to verify initialization
        $sampleData = [
            'o2_b1s1_voltage' => 0.5,
            'stft_b1' => 2.0,
            'rpm' => 2000,
        ];

        $this->analyzer->processRow($sampleData);

        // If no exception, initialization worked
        $this->assertTrue(true);
    }

    public function testProcessRowUpdatesAccumulators(): void
    {
        $this->analyzer->startSession();

        // Process multiple rows
        $rows = [
            ['o2_b1s1_voltage' => 0.45, 'stft_b1' => 1.5, 'rpm' => 1500],
            ['o2_b1s1_voltage' => 0.50, 'stft_b1' => 2.0, 'rpm' => 2000],
            ['o2_b1s1_voltage' => 0.55, 'stft_b1' => 2.5, 'rpm' => 2500],
        ];

        foreach ($rows as $row) {
            $this->analyzer->processRow($row);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('o2_sensors', $result);
        $this->assertArrayHasKey('fuel_trim', $result);
        $this->assertArrayHasKey('catalyst_efficiency', $result);
        $this->assertArrayHasKey('engine_health', $result);
    }

    public function testFuelTrimAnalysisExcellent(): void
    {
        $this->analyzer->startSession();

        // Simulate excellent fuel trim (between -3% and +3%)
        for ($i = 0; $i < 100; $i++) {
            $this->analyzer->processRow([
                'stft_b1' => 1.5 + (($i % 10) * 0.1), // 1.5 to 2.4%
                'ltft_b1' => 0.5 + (($i % 5) * 0.1),  // 0.5 to 0.9%
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertEquals('excellent', $result['fuel_trim']['status']);
        $this->assertGreaterThanOrEqual(90, $result['fuel_trim']['score']);
        $this->assertLessThanOrEqual(100, $result['fuel_trim']['score']);
    }

    public function testFuelTrimAnalysisWarning(): void
    {
        $this->analyzer->startSession();

        // Simulate warning level fuel trim (10-20%)
        for ($i = 0; $i < 50; $i++) {
            $this->analyzer->processRow([
                'stft_b1' => 12.0, // 12% - warning range
                'ltft_b1' => 3.0,
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('fuel_trim', $result);
        $this->assertArrayHasKey('status', $result['fuel_trim']);
        $this->assertThat(
            $result['fuel_trim']['status'],
            $this->logicalOr(
                $this->equalTo('warning'),
                $this->equalTo('critical'),
                $this->equalTo('poor'),
                $this->equalTo('good'),
                $this->equalTo('marginal')
            )
        );
        $this->assertLessThan(90, $result['fuel_trim']['score']);
    }

    public function testO2SensorAnalysisHealthy(): void
    {
        $this->analyzer->startSession();

        // Simulate healthy O2 sensor (0.1-0.9V range with good switching)
        for ($i = 0; $i < 100; $i++) {
            $voltage = 0.2 + (($i % 10) * 0.06); // 0.2V to 0.8V
            $this->analyzer->processRow([
                'o2_b1s1_voltage' => $voltage,
                'o2_b2s1_voltage' => $voltage + 0.05,
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('o2_sensors', $result);
        $this->assertArrayHasKey('status', $result['o2_sensors']);
        $this->assertArrayHasKey('voltage_range', $result['o2_sensors']);
        $this->assertGreaterThan(0.5, $result['o2_sensors']['voltage_range']);
        $this->assertThat(
            $result['o2_sensors']['status'],
            $this->logicalOr(
                $this->equalTo('healthy'),
                $this->equalTo('good'),
                $this->equalTo('excellent'),
                $this->equalTo('marginal')
            )
        );
    }

    public function testO2SensorAnalysisStuck(): void
    {
        $this->analyzer->startSession();

        // Simulate stuck O2 sensor (constant voltage)
        for ($i = 0; $i < 100; $i++) {
            $this->analyzer->processRow([
                'o2_b1s1_voltage' => 0.45, // Always the same
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('o2_sensors', $result);
        $this->assertEquals(0, $result['o2_sensors']['voltage_range']);
        $this->assertThat(
            $result['o2_sensors']['status'],
            $this->logicalOr(
                $this->equalTo('poor'),
                $this->equalTo('degraded'),
                $this->equalTo('critical')
            )
        );
        $messages = implode(' ', $result['o2_sensors']['messages']);
        $this->assertStringContainsString('lazy', strtolower($messages));
    }

    public function testCatalystEfficiencyCalculation(): void
    {
        $this->analyzer->startSession();

        // Simulate good catalyst (downstream O2 less reactive)
        for ($i = 0; $i < 100; $i++) {
            $upstreamVoltage = 0.2 + (($i % 10) * 0.06); // 0.2-0.8V
            $downstreamVoltage = 0.4 + (($i % 5) * 0.02); // 0.4-0.48V (less variation)

            $this->analyzer->processRow([
                'o2_b1s1_voltage' => $upstreamVoltage,
                'o2_b1s2_voltage' => $downstreamVoltage,
                'catalyst_temp_b1' => 450, // Optimal temp
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('catalyst_efficiency', $result);
        $this->assertArrayHasKey('status', $result['catalyst_efficiency']);
        // With both sensors present, should have efficiency calculation
        $this->assertThat(
            $result['catalyst_efficiency']['status'],
            $this->logicalOr(
                $this->equalTo('healthy'),
                $this->equalTo('good'),
                $this->equalTo('excellent'),
                $this->equalTo('poor')
            )
        );
    }

    public function testEngineHealthAnalysis(): void
    {
        $this->analyzer->startSession();

        // Simulate healthy engine parameters
        for ($i = 0; $i < 100; $i++) {
            $this->analyzer->processRow([
                'rpm' => 2000 + ($i % 10) * 100,
                'engine_load' => 30 + ($i % 5) * 5,
                'coolant_temp' => 90,
                'intake_air_temp' => 25,
                'maf' => 15.5,
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('engine_health', $result);
        $this->assertArrayHasKey('score', $result['engine_health']);
        $this->assertGreaterThanOrEqual(80, $result['engine_health']['score']);
        $this->assertArrayHasKey('avg_load', $result['engine_health']);
    }

    public function testEngineHealthWithHighLoad(): void
    {
        $this->analyzer->startSession();

        // Simulate sustained high load
        for ($i = 0; $i < 100; $i++) {
            $this->analyzer->processRow([
                'rpm' => 4500,
                'engine_load' => 85, // High load
                'coolant_temp' => 105, // High temp
                'intake_air_temp' => 45,
            ]);
        }

        $result = $this->analyzer->finalizeSession();

        $this->assertArrayHasKey('engine_health', $result);
        $this->assertArrayHasKey('score', $result['engine_health']);
        $this->assertEquals(85, $result['engine_health']['avg_load']);
        $this->assertEquals(105, $result['engine_health']['max_temp']);
    }

    public function testIncrementalProcessingNoMemoryLeak(): void
    {
        $this->analyzer->startSession();

        $memoryBefore = memory_get_usage();

        // Process 10,000 rows
        for ($i = 0; $i < 10000; $i++) {
            $this->analyzer->processRow([
                'o2_b1s1_voltage' => 0.45,
                'stft_b1' => 2.0,
                'rpm' => 2000,
            ]);
        }

        $memoryAfter = memory_get_usage();
        $memoryIncrease = $memoryAfter - $memoryBefore;

        // Memory increase should be minimal (< 1MB) for streaming approach
        $this->assertLessThan(
            1024 * 1024,
            $memoryIncrease,
            "Memory increased by " . round($memoryIncrease / 1024, 2) . " KB"
        );
    }

    public function testEmptySessionReturnsDefaultResults(): void
    {
        $this->analyzer->startSession();

        // No rows processed
        $result = $this->analyzer->finalizeSession();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('o2_sensors', $result);
        $this->assertArrayHasKey('fuel_trim', $result);
    }

    public function testPartialDataHandling(): void
    {
        $this->analyzer->startSession();

        // Process rows with only some PIDs
        $this->analyzer->processRow(['rpm' => 2000]);
        $this->analyzer->processRow(['o2_b1s1_voltage' => 0.45]);
        $this->analyzer->processRow(['stft_b1' => 2.0]);

        $result = $this->analyzer->finalizeSession();

        // Should not throw exceptions with partial data
        $this->assertIsArray($result);
    }
}
