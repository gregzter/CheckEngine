<?php

namespace App\Service\Diagnostic;

use Psr\Log\LoggerInterface;

/**
 * Streaming Diagnostic Analyzer
 *
 * Analyzes OBD2 data incrementally without loading all data in RAM.
 * Calculates diagnostics on-the-fly as data points are processed.
 */
class StreamingDiagnosticAnalyzer
{
    private array $accumulators = [];
    private int $sampleCount = 0;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Initialize a new analysis session
     */
    public function startSession(): void
    {
        $this->accumulators = [
            // O2 Sensors
            'o2_b1s1_voltage' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN],
            'o2_b1s2_voltage' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN],

            // Fuel Trims
            'stft_b1' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN, 'values' => []],
            'ltft_b1' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN, 'values' => []],

            // Catalyst Temperature
            'catalyst_temp_b1s1' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN],
            'catalyst_temp_b1s2' => ['sum' => 0, 'count' => 0, 'min' => PHP_FLOAT_MAX, 'max' => PHP_FLOAT_MIN],

            // Engine metrics
            'engine_rpm' => ['sum' => 0, 'count' => 0, 'max' => PHP_FLOAT_MIN],
            'engine_load' => ['sum' => 0, 'count' => 0],
            'coolant_temp' => ['sum' => 0, 'count' => 0, 'max' => PHP_FLOAT_MIN],
        ];

        $this->sampleCount = 0;
    }

    /**
     * Process a single data row incrementally
     *
     * @param array $parsedRow Associative array [pid_name => value]
     */
    public function processRow(array $parsedRow): void
    {
        $this->sampleCount++;

        foreach ($parsedRow as $pidName => $value) {
            if ($value === null || !isset($this->accumulators[$pidName])) {
                continue;
            }

            $acc = &$this->accumulators[$pidName];

            // Update statistics
            $acc['sum'] += $value;
            $acc['count']++;

            if (isset($acc['min'])) {
                $acc['min'] = min($acc['min'], $value);
            }
            if (isset($acc['max'])) {
                $acc['max'] = max($acc['max'], $value);
            }

            // Store recent values for fuel trims (last 100 samples)
            if (isset($acc['values'])) {
                $acc['values'][] = $value;
                if (count($acc['values']) > 100) {
                    array_shift($acc['values']);
                }
            }
        }
    }

    /**
     * Finalize analysis and return diagnostic results
     *
     * @return array Diagnostic results with scores and recommendations
     */
    public function finalizeSession(): array
    {
        $diagnostics = [
            'sample_count' => $this->sampleCount,
            'catalyst_efficiency' => $this->analyzeCatalystEfficiency(),
            'fuel_trim' => $this->analyzeFuelTrims(),
            'o2_sensors' => $this->analyzeO2Sensors(),
            'engine_health' => $this->analyzeEngineHealth(),
        ];

        $this->logger->info("Diagnostic analysis complete", [
            'samples' => $this->sampleCount,
            'catalyst_score' => $diagnostics['catalyst_efficiency']['score'] ?? null,
            'fuel_trim_score' => $diagnostics['fuel_trim']['score'] ?? null,
        ]);

        return $diagnostics;
    }

    /**
     * Analyze catalyst efficiency from O2 sensors
     */
    private function analyzeCatalystEfficiency(): array
    {
        $o2_upstream = $this->accumulators['o2_b1s1_voltage'];
        $o2_downstream = $this->accumulators['o2_b1s2_voltage'];

        if ($o2_upstream['count'] < 10 || $o2_downstream['count'] < 10) {
            return [
                'status' => 'insufficient_data',
                'score' => null,
                'message' => 'Not enough O2 sensor data for catalyst analysis',
            ];
        }

        $avg_upstream = $o2_upstream['sum'] / $o2_upstream['count'];
        $avg_downstream = $o2_downstream['sum'] / $o2_downstream['count'];

        // Calculate voltage swing (activity)
        $swing_upstream = $o2_upstream['max'] - $o2_upstream['min'];
        $swing_downstream = $o2_downstream['max'] - $o2_downstream['min'];

        // Healthy catalyst: downstream should be stable, upstream should switch
        $efficiency = $swing_downstream > 0 ? ($swing_upstream / $swing_downstream) : 0;

        // Score: >2.5 = excellent, 1.5-2.5 = good, 1.0-1.5 = marginal, <1.0 = poor
        $score = 100;
        $status = 'excellent';
        $messages = [];

        if ($efficiency < 1.0) {
            $score = 40;
            $status = 'poor';
            $messages[] = "Catalyst efficiency degraded (ratio: " . round($efficiency, 2) . ")";
        } elseif ($efficiency < 1.5) {
            $score = 65;
            $status = 'marginal';
            $messages[] = "Catalyst efficiency marginal (ratio: " . round($efficiency, 2) . ")";
        } elseif ($efficiency < 2.5) {
            $score = 85;
            $status = 'good';
        }

        return [
            'status' => $status,
            'score' => $score,
            'efficiency_ratio' => round($efficiency, 2),
            'upstream_voltage' => round($avg_upstream, 3),
            'downstream_voltage' => round($avg_downstream, 3),
            'messages' => $messages,
        ];
    }

    /**
     * Analyze fuel trim values
     */
    private function analyzeFuelTrims(): array
    {
        $stft = $this->accumulators['stft_b1'];
        $ltft = $this->accumulators['ltft_b1'];

        if ($stft['count'] < 10) {
            return [
                'status' => 'insufficient_data',
                'score' => null,
                'message' => 'Not enough fuel trim data',
            ];
        }

        $avg_stft = $stft['sum'] / $stft['count'];
        $avg_ltft = $ltft['count'] > 0 ? ($ltft['sum'] / $ltft['count']) : 0;

        // Calculate standard deviation for STFT
        $variance = 0;
        if (!empty($stft['values'])) {
            foreach ($stft['values'] as $value) {
                $variance += pow($value - $avg_stft, 2);
            }
            $variance /= count($stft['values']);
        }
        $stddev_stft = sqrt($variance);

        // Score based on fuel trim values
        // Ideal: -5% to +5%, Acceptable: -10% to +10%, Poor: beyond ±15%
        $total_trim = abs($avg_stft) + abs($avg_ltft);

        $score = 100;
        $status = 'excellent';
        $messages = [];

        if ($total_trim > 15) {
            $score = 50;
            $status = 'poor';
            $messages[] = "Fuel trim excessive: " . round($total_trim, 1) . "% (check for vacuum leaks or MAF issues)";
        } elseif ($total_trim > 10) {
            $score = 70;
            $status = 'marginal';
            $messages[] = "Fuel trim elevated: " . round($total_trim, 1) . "%";
        } elseif ($total_trim > 5) {
            $score = 85;
            $status = 'good';
        }

        // Check for unstable fuel trim (high variance)
        if ($stddev_stft > 3) {
            $score = min($score, 75);
            $messages[] = "Fuel trim unstable (stddev: " . round($stddev_stft, 1) . "%)";
        }

        return [
            'status' => $status,
            'score' => $score,
            'short_term_avg' => round($avg_stft, 2),
            'long_term_avg' => round($avg_ltft, 2),
            'total_trim' => round($total_trim, 2),
            'stft_stddev' => round($stddev_stft, 2),
            'messages' => $messages,
        ];
    }

    /**
     * Analyze O2 sensor health
     */
    private function analyzeO2Sensors(): array
    {
        $o2_b1s1 = $this->accumulators['o2_b1s1_voltage'];

        if ($o2_b1s1['count'] < 10) {
            return [
                'status' => 'insufficient_data',
                'score' => null,
            ];
        }

        $avg = $o2_b1s1['sum'] / $o2_b1s1['count'];
        $range = $o2_b1s1['max'] - $o2_b1s1['min'];

        $score = 100;
        $status = 'excellent';
        $messages = [];

        // O2 sensor should switch between ~0.1V and ~0.9V
        if ($range < 0.5) {
            $score = 60;
            $status = 'poor';
            $messages[] = "O2 sensor lazy (range: " . round($range, 2) . "V, expected >0.6V)";
        } elseif ($range < 0.6) {
            $score = 80;
            $status = 'marginal';
            $messages[] = "O2 sensor response marginal (range: " . round($range, 2) . "V)";
        }

        // Check if stuck lean or rich
        if ($avg < 0.3) {
            $messages[] = "O2 sensor reading lean (avg: " . round($avg, 3) . "V)";
        } elseif ($avg > 0.7) {
            $messages[] = "O2 sensor reading rich (avg: " . round($avg, 3) . "V)";
        }

        return [
            'status' => $status,
            'score' => $score,
            'average_voltage' => round($avg, 3),
            'voltage_range' => round($range, 3),
            'messages' => $messages,
        ];
    }

    /**
     * Analyze general engine health
     */
    private function analyzeEngineHealth(): array
    {
        $rpm = $this->accumulators['engine_rpm'];
        $load = $this->accumulators['engine_load'];
        $temp = $this->accumulators['coolant_temp'];

        $score = 100;
        $messages = [];

        // Check for overheating
        if ($temp['count'] > 0) {
            $max_temp = $temp['max'];
            if ($max_temp > 105) {
                $score = min($score, 50);
                $messages[] = "Engine overheating detected (max: {$max_temp}°C)";
            } elseif ($max_temp > 95) {
                $score = min($score, 80);
                $messages[] = "Engine running hot (max: {$max_temp}°C)";
            }
        }

        // Check for over-revving
        if ($rpm['count'] > 0 && $rpm['max'] > 6000) {
            $messages[] = "High RPM detected (max: {$rpm['max']} RPM)";
        }

        return [
            'score' => $score,
            'max_rpm' => $rpm['max'] ?? null,
            'avg_load' => $load['count'] > 0 ? round($load['sum'] / $load['count'], 1) : null,
            'max_temp' => $temp['max'] ?? null,
            'messages' => $messages,
        ];
    }
}
