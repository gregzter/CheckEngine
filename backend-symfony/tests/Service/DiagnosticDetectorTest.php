<?php

namespace App\Tests\Service;

use App\Service\DiagnosticDetector;
use PHPUnit\Framework\TestCase;

class DiagnosticDetectorTest extends TestCase
{
    private DiagnosticDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DiagnosticDetector();
    }

    public function testValidateColumnDataWithGoodData(): void
    {
        // Données réalistes de RPM
        $columnData = array_merge(
            array_fill(0, 100, 800),    // Ralenti
            array_fill(0, 100, 2000),   // Conduite normale
            array_fill(0, 50, 3500),    // Accélération
            array_fill(0, 50, 0)        // Moteur arrêté (mode électrique)
        );

        $result = $this->detector->validateColumnData($columnData, 'engine_rpm');

        $this->assertTrue($result['valid']);
        $this->assertEquals('valid', $result['reason']);
        $this->assertEquals(300, $result['stats']['total_rows']);
        $this->assertEquals(300, $result['stats']['valid_count']);
        $this->assertEquals(100.0, $result['stats']['valid_rate']);
    }

    public function testValidateColumnDataWithAllZeros(): void
    {
        // Colonne température avec que des zéros = capteur débranché
        $columnData = array_fill(0, 100, 0);

        $result = $this->detector->validateColumnData($columnData, 'coolant_temp');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('all_zeros_temp', $result['reason']);
    }

    public function testValidateColumnDataWithEmptyValues(): void
    {
        // Colonne avec beaucoup de valeurs vides
        $columnData = array_merge(
            array_fill(0, 90, ''),      // Vides
            array_fill(0, 5, '-'),      // Invalides
            array_fill(0, 5, 100)       // Seulement 5% valides
        );

        $result = $this->detector->validateColumnData($columnData, 'o2_b1s1_voltage');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('insufficient_data', $result['reason']);
        $this->assertEquals(5.0, $result['stats']['valid_rate']);
    }

    public function testValidateColumnDataWithFrozenO2Sensor(): void
    {
        // Sonde O2 figée = défectueuse
        $columnData = array_fill(0, 100, 0.45);

        $result = $this->detector->validateColumnData($columnData, 'o2_b1s1_voltage');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('frozen_value', $result['reason']);
        $this->assertEquals(0.45, $result['stats']['min']);
        $this->assertEquals(0.45, $result['stats']['max']);
    }

    public function testValidateColumnDataWithMixedQuality(): void
    {
        // 60% de bonnes données, 40% de vides
        $columnData = array_merge(
            array_fill(0, 60, 85.5),    // Température coolant normale
            array_fill(0, 40, '')       // Valeurs manquantes
        );

        $result = $this->detector->validateColumnData($columnData, 'coolant_temp');

        $this->assertTrue($result['valid']); // 60% > 30% requis
        $this->assertEquals(60.0, $result['stats']['valid_rate']);
        $this->assertEquals(85.5, $result['stats']['avg']);
    }

    public function testValidateColumnDataWithErrorValues(): void
    {
        // Colonne avec valeurs d'erreur Prius (51199 = Active Test not performed)
        $columnData = array_fill(0, 100, 51199);

        $result = $this->detector->validateColumnData($columnData, 'prius_rpm_cyl1');

        $this->assertFalse($result['valid']);
        $this->assertEquals(100, $result['stats']['error_value_count']);
        $this->assertEquals(0, $result['stats']['valid_count']);
    }

    public function testCalculateColumnCorrelation(): void
    {
        // Colonnes identiques
        $data1 = [100, 200, 300, 400, 500];
        $data2 = [100, 200, 300, 400, 500];

        $correlation = $this->detector->calculateColumnCorrelation($data1, $data2);
        $this->assertEquals(100.0, $correlation); // Parfaitement identique

        // Colonnes très similaires (< 1% différence)
        $data3 = [100, 200, 300, 400, 500];
        $data4 = [101, 199, 301, 399, 502]; // ~0.5-1% différence

        $correlation2 = $this->detector->calculateColumnCorrelation($data3, $data4);
        $this->assertGreaterThanOrEqual(40, $correlation2); // Considérées comme proches
        $this->assertLessThanOrEqual(60, $correlation2);

        // Colonnes avec différence < 2% (très proche)
        $data5 = [1000, 2000, 3000, 4000, 5000];
        $data6 = [1005, 2005, 3005, 4005, 5005]; // 0.5% différence

        $correlation3 = $this->detector->calculateColumnCorrelation($data5, $data6);
        $this->assertGreaterThanOrEqual(60, $correlation3); // Assez proche

        // Colonnes totalement différentes
        $data7 = [100, 200, 300, 400, 500];
        $data8 = [1000, 2000, 3000, 4000, 5000]; // 10x différence

        $correlation4 = $this->detector->calculateColumnCorrelation($data7, $data8);
        $this->assertEquals(0.0, $correlation4); // Pas du tout corrélé
    }

    public function testSelectBestColumnVerifiesCorrelation(): void
    {
        $duplicates = [
            [
                'db_name' => 'engine_rpm',
                'csv_column' => 'Engine RPM(rpm)',
                'priority' => 0,
                'data' => [2000, 2100, 2200, 2300, 2400] // Source 1
            ],
            [
                'db_name' => 'engine_rpm',
                'csv_column' => 'ECU(7EA): Engine RPM(rpm)',
                'priority' => 1,
                'data' => [2000, 2100, 2200, 2300, 2400] // Source 2 identique
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates, true);

        $this->assertNotNull($best);
        $this->assertEquals('Engine RPM(rpm)', $best['csv_column']);
        $this->assertEquals(0, $best['priority']); // Garde la meilleure priorité
    }

    public function testSelectBestColumnRejectsNonCorrelatedDuplicates(): void
    {
        $duplicates = [
            [
                'db_name' => 'vehicle_speed',
                'csv_column' => 'Speed (OBD)(km/h)',
                'priority' => 0,
                'data' => array_fill(0, 100, 50) // Vitesse constante
            ],
            [
                'db_name' => 'vehicle_speed',
                'csv_column' => 'GPS Speed (Meters/second)',
                'priority' => 2,
                'data' => array_merge( // Données très différentes
                    array_fill(0, 50, 0),
                    array_fill(0, 50, 100)
                )
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates, true);

        // Devrait sélectionner la première (meilleure priorité) car pas vraiment corrélées
        $this->assertNotNull($best);
        $this->assertEquals('Speed (OBD)(km/h)', $best['csv_column']);
    }

    public function testSelectBestColumnWithDuplicates(): void
    {
        $duplicates = [
            [
                'db_name' => 'engine_rpm',
                'csv_column' => 'Engine RPM(rpm)',
                'priority' => 0,
                'data' => array_fill(0, 100, 2000) // Bonnes données
            ],
            [
                'db_name' => 'engine_rpm',
                'csv_column' => 'ECU(7EA): Engine RPM(rpm)',
                'priority' => 1,
                'data' => array_fill(0, 100, 0) // Toutes à zéro
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates);

        $this->assertNotNull($best);
        $this->assertEquals('Engine RPM(rpm)', $best['csv_column']);
        $this->assertEquals(0, $best['priority']);
    }

    public function testSelectBestColumnPrefersLowerPriorityIfBothValid(): void
    {
        $duplicates = [
            [
                'db_name' => 'coolant_temp',
                'csv_column' => 'ECU(7EA): Engine Coolant Temperature(°C)',
                'priority' => 1,
                'data' => array_fill(0, 100, 85) // Bonnes données mais priorité 1
            ],
            [
                'db_name' => 'coolant_temp',
                'csv_column' => 'Engine Coolant Temperature(°C)',
                'priority' => 0,
                'data' => array_fill(0, 100, 86) // Bonnes données priorité 0
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates);

        $this->assertNotNull($best);
        $this->assertEquals('Engine Coolant Temperature(°C)', $best['csv_column']);
        $this->assertEquals(0, $best['priority']);
    }

    public function testSelectBestColumnWhenOnlySecondaryIsValid(): void
    {
        $duplicates = [
            [
                'db_name' => 'coolant_temp',
                'csv_column' => 'Engine Coolant Temperature(°C)',
                'priority' => 0,
                'data' => array_fill(0, 100, 0) // Toutes à zéro = capteur défaillant
            ],
            [
                'db_name' => 'coolant_temp',
                'csv_column' => 'ECU(7EA): Engine Coolant Temperature(°C)',
                'priority' => 1,
                'data' => array_fill(0, 100, 85) // Bonnes données de l'ECU secondaire
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates);

        $this->assertNotNull($best);
        $this->assertEquals('ECU(7EA): Engine Coolant Temperature(°C)', $best['csv_column']);
        $this->assertEquals(1, $best['priority']); // Utilise la source secondaire car la principale est invalide
    }

    public function testSelectBestColumnReturnsNullWhenAllInvalid(): void
    {
        $duplicates = [
            [
                'db_name' => 'o2_b1s1_voltage',
                'csv_column' => 'O2 Bank 1 Sensor 1 Voltage(V)',
                'priority' => 0,
                'data' => array_fill(0, 100, '') // Vide
            ],
            [
                'db_name' => 'o2_b1s1_voltage',
                'csv_column' => 'O2 B1S1(V)',
                'priority' => 1,
                'data' => array_fill(0, 100, 0.45) // Figée
            ]
        ];

        $best = $this->detector->selectBestColumn($duplicates);

        $this->assertNull($best); // Aucune source valide
    }

    public function testDetectAvailableDiagnosticsWithCompleteData(): void
    {
        $availableColumns = [
            'o2_b1s1_voltage' => [
                'data' => $this->generateO2Data(),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'o2_b1s2_voltage' => [
                'data' => $this->generateO2Data(),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'engine_rpm' => [
                'data' => array_fill(0, 100, 2000),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'catalyst_temp_b1s1' => [
                'data' => array_fill(0, 100, 450),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'catalyst_temp_b1s2' => [
                'data' => array_fill(0, 100, 420),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'vehicle_speed' => [
                'data' => array_fill(0, 100, 50),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'engine_load' => [
                'data' => array_fill(0, 100, 45),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'coolant_temp' => [
                'data' => array_fill(0, 100, 85),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            'stft_b1' => [
                'data' => array_fill(0, 100, 2.5),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ]
        ];

        $diagnostics = $this->detector->detectAvailableDiagnostics($availableColumns);

        $this->assertArrayHasKey('catalyst', $diagnostics);
        $this->assertTrue($diagnostics['catalyst']['available']);
        $this->assertGreaterThanOrEqual(85, $diagnostics['catalyst']['completeness']); // Avec 6/7 recommandées
        $this->assertEquals('high', $diagnostics['catalyst']['confidence']);
    }

    public function testDetectAvailableDiagnosticsWithMissingMandatory(): void
    {
        $availableColumns = [
            'o2_b1s1_voltage' => [
                'data' => $this->generateO2Data(),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ],
            // Manque o2_b1s2_voltage et engine_rpm (obligatoires)
            'vehicle_speed' => [
                'data' => array_fill(0, 100, 50),
                'validation' => ['valid' => true, 'stats' => ['valid_rate' => 100]]
            ]
        ];

        $diagnostics = $this->detector->detectAvailableDiagnostics($availableColumns);

        $this->assertArrayHasKey('catalyst', $diagnostics);
        $this->assertFalse($diagnostics['catalyst']['available']); // Manque des colonnes obligatoires
        $this->assertContains('o2_b1s2_voltage', $diagnostics['catalyst']['missing_mandatory']);
        $this->assertContains('engine_rpm', $diagnostics['catalyst']['missing_mandatory']);
    }

    public function testGetDiagnosticRequirements(): void
    {
        $requirements = $this->detector->getDiagnosticRequirements('catalyst');

        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('mandatory', $requirements);
        $this->assertArrayHasKey('recommended', $requirements);
        $this->assertArrayHasKey('optional', $requirements);
        $this->assertContains('o2_b1s1_voltage', $requirements['mandatory']);
        $this->assertContains('catalyst_temp_b1s1', $requirements['recommended']);
    }

    public function testGetAllDiagnosticTypes(): void
    {
        $types = $this->detector->getAllDiagnosticTypes();

        $this->assertIsArray($types);
        $this->assertContains('catalyst', $types);
        $this->assertContains('o2_sensors', $types);
        $this->assertContains('engine', $types);
        $this->assertContains('driving', $types);
        $this->assertContains('hybrid', $types);
    }

    /**
     * Helper: Génère des données réalistes de sonde O2
     */
    private function generateO2Data(): array
    {
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            // Oscillation typique d'une sonde O2 entre 0.1 et 0.9V
            $data[] = 0.5 + (sin($i / 5) * 0.4);
        }
        return $data;
    }
}
