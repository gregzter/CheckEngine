<?php

namespace App\Tests\Service;

use App\Repository\OBD2ColumnVariantRepository;
use App\Service\OBD2ColumnMapper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests pour OBD2ColumnMapper - utilise maintenant la base de données
 */
class OBD2ColumnMapperTest extends KernelTestCase
{
    private OBD2ColumnMapper $mapper;

    protected function setUp(): void
    {
        self::bootKernel();

        // Récupérer le repository depuis le container
        $variantRepo = static::getContainer()->get(OBD2ColumnVariantRepository::class);

        // Créer le mapper manuellement avec le repository
        $this->mapper = new OBD2ColumnMapper($variantRepo);
    }
    public function testNormalizeColumnName(): void
    {
        // Test colonnes standard
        $this->assertEquals('timestamp_gps', $this->mapper->normalizeColumnName('GPS Time'));
        $this->assertEquals('engine_rpm', $this->mapper->normalizeColumnName('Engine RPM(rpm)'));
        $this->assertEquals('o2_b1s1_voltage', $this->mapper->normalizeColumnName('O2 Bank 1 Sensor 1 Voltage(V)'));

        // Test colonnes Prius
        $this->assertEquals('prius_af_lambda', $this->mapper->normalizeColumnName('[PRIUS]AF Lambda B1S1'));
        $this->assertEquals('prius_coolant_7e0', $this->mapper->normalizeColumnName('[PRIUS]Engine Coolant Temperature_7E0(°C)'));

        // Test colonne inconnue
        $this->assertNull($this->mapper->normalizeColumnName('Unknown Column Name'));

        // Test insensible à la casse
        $this->assertEquals('vehicle_speed', $this->mapper->normalizeColumnName('speed (obd)(km/h)'));

        // Test avec espaces
        $this->assertEquals('latitude', $this->mapper->normalizeColumnName('  Latitude  '));
    }

    public function testMapCsvHeadersBasic(): void
    {
        $csvHeaders = [
            'GPS Time',
            'Engine RPM(rpm)',
            'O2 Bank 1 Sensor 1 Voltage(V)',
            'Unknown Column'
        ];

        $result = $this->mapper->mapCsvHeaders($csvHeaders);

        // Vérifier colonnes mappées
        $this->assertArrayHasKey('timestamp_gps', $result['mapped']);
        $this->assertArrayHasKey('engine_rpm', $result['mapped']);
        $this->assertArrayHasKey('o2_b1s1_voltage', $result['mapped']);

        // Vérifier colonnes non mappées
        $this->assertContains('Unknown Column', $result['unmapped']);

        // Vérifier index
        $this->assertEquals(0, $result['mapped']['timestamp_gps']['index']);
        $this->assertEquals(1, $result['mapped']['engine_rpm']['index']);
        $this->assertEquals(2, $result['mapped']['o2_b1s1_voltage']['index']);
    }

    public function testMapCsvHeadersWithDuplicates(): void
    {
        // Simulation d'un CSV avec plusieurs sources pour le RPM et la température
        $csvHeaders = [
            'Engine RPM(rpm)',                              // Index 0 - Priorité 0 (meilleure)
            '[PRIUS]Engine Speed_7E0(RPM)',                 // Index 1 - Priorité 0 (variante Prius)
            '[PRIUS]Engine Speed_7E2(RPM)',                 // Index 2 - Priorité 0 (autre variante Prius)
            'Engine Coolant Temperature(°C)',               // Index 3 - Priorité 0 (meilleure)
            '[PRIUS]Engine Coolant Temperature_7E0(°C)',    // Index 4 - Priorité 0 (variante)
            'GPS Time'                                       // Index 5 - Pas de doublon
        ];

        $result = $this->mapper->mapCsvHeaders($csvHeaders);

        // Vérifier que engine_rpm utilise la meilleure source (Engine RPM standard)
        $this->assertEquals('engine_rpm', $this->mapper->normalizeColumnName('Engine RPM(rpm)'));
        $this->assertArrayHasKey('engine_rpm', $result['mapped']);
        $this->assertEquals(0, $result['mapped']['engine_rpm']['index']); // Devrait garder le standard

        // Vérifier les variantes Prius sont mappées séparément
        $this->assertArrayHasKey('prius_rpm_7e0', $result['mapped']);
        $this->assertArrayHasKey('prius_rpm_7e2', $result['mapped']);

        // Vérifier coolant_temp utilise la meilleure source
        $this->assertArrayHasKey('coolant_temp', $result['mapped']);
        $this->assertEquals(3, $result['mapped']['coolant_temp']['index']);

        // Vérifier variante Prius coolant
        $this->assertArrayHasKey('prius_coolant_7e0', $result['mapped']);

        // Vérifier qu'il n'y a pas de doublons détectés (car les variantes Prius sont différentes)
        $this->assertEmpty($result['duplicates']);
    }

    public function testMapCsvHeadersWithRealDuplicates(): void
    {
        // Test avec de vrais doublons (même donnée, noms différents)
        $csvHeaders = [
            'RPM',                      // Index 0 - Priorité 3 pour engine_rpm
            'Engine RPM(rpm)',          // Index 1 - Priorité 0 pour engine_rpm (meilleure)
            'Engine Speed(rpm)',        // Index 2 - Priorité 1 pour engine_rpm
        ];

        $result = $this->mapper->mapCsvHeaders($csvHeaders);

        // Devrait mapper vers engine_rpm et garder la meilleure source
        $this->assertCount(1, $result['mapped']); // Une seule donnée mappée
        $this->assertArrayHasKey('engine_rpm', $result['mapped']);

        // Devrait utiliser "Engine RPM(rpm)" (priorité 0)
        $this->assertEquals('Engine RPM(rpm)', $result['mapped']['engine_rpm']['csv_column']);
        $this->assertEquals(1, $result['mapped']['engine_rpm']['index']);
        $this->assertEquals(0, $result['mapped']['engine_rpm']['priority']);

        // Devrait détecter les doublons
        $this->assertArrayHasKey('engine_rpm', $result['duplicates']);
        $this->assertCount(3, $result['duplicates']['engine_rpm']); // 3 sources différentes
    }

    public function testGetAvailableColumns(): void
    {
        $csvHeaders = [
            'GPS Time',
            'Engine RPM(rpm)',
            'O2 Bank 1 Sensor 1 Voltage(V)',
            'Catalyst Temperature (Bank 1 Sensor 1)(°C)',
            'Unknown Column'
        ];

        $available = $this->mapper->getAvailableColumns($csvHeaders);

        $this->assertContains('timestamp_gps', $available);
        $this->assertContains('engine_rpm', $available);
        $this->assertContains('o2_b1s1_voltage', $available);
        $this->assertContains('catalyst_temp_b1s1', $available);
        $this->assertNotContains('unknown_column', $available);
    }

    public function testGetMappingStats(): void
    {
        $csvHeaders = [
            'GPS Time',                 // Mappé
            'Engine RPM(rpm)',          // Mappé
            'Unknown1',                 // Non mappé
            'Unknown2',                 // Non mappé
            'Vehicle Speed(km/h)',      // Mappé
            'Speed (OBD)(km/h)'         // Doublon avec Vehicle Speed
        ];

        $stats = $this->mapper->getMappingStats($csvHeaders);

        $this->assertEquals(6, $stats['total_columns']);
        $this->assertGreaterThanOrEqual(3, $stats['mapped_columns']); // Au moins 3 mappées
        $this->assertEquals(2, $stats['unmapped_columns']);
        $this->assertGreaterThan(0, $stats['mapping_rate']);
    }

    public function testGetAllKnownColumns(): void
    {
        $columns = $this->mapper->getAllKnownColumns();

        $this->assertIsArray($columns);
        $this->assertContains('timestamp_gps', $columns);
        $this->assertContains('engine_rpm', $columns);
        $this->assertContains('o2_b1s1_voltage', $columns);
        $this->assertContains('prius_af_lambda', $columns);
    }

    public function testGetVariants(): void
    {
        $variants = $this->mapper->getVariants('engine_rpm');

        $this->assertIsArray($variants);
        $this->assertContains('Engine RPM(rpm)', $variants);
        $this->assertContains('Engine Speed(rpm)', $variants);
        $this->assertContains('RPM', $variants);

        // Test colonne inexistante
        $this->assertNull($this->mapper->getVariants('nonexistent_column'));
    }

    /**
     * Test avec le header réel du fichier exemple fourni
     */
    public function testRealWorldExample(): void
    {
        $csvHeaders = [
            'GPS Time',
            'Device Time',
            'Longitude',
            'Latitude',
            'GPS Speed (Meters/second)',
            'Horizontal Dilution of Precision',
            'Altitude',
            'Bearing',
            'G(x)',
            'G(y)',
            'G(z)',
            'G(calibrated)',
            '[PRIUS]AF Lambda B1S1',
            '[PRIUS]AFS Voltage B1S1(V)',
            '[PRIUS]All Cylinders Misfire Count',
            '[PRIUS]Coolant Temperature_7C0(°C)',
            '[PRIUS]Coolant Temperature_7E0(°C)',
            '[PRIUS]Engine Coolant Temp_7C4(°C)',
            '[PRIUS]Engine Coolant Temperature_7E2(°C)',
            '[PRIUS]Engine Speed of Cyl #1 (51199 rpm: Active Test not performed)(RPM)',
            '[PRIUS]Engine Speed of Cyl #2 (51199 rpm: Active Test not performed)(RPM)',
            '[PRIUS]Engine Speed of Cyl #3 (51199 rpm: Active Test not performed)(RPM)',
            '[PRIUS]Engine Speed of Cyl #4 (51199 rpm: Active Test not performed)(RPM)',
            '[PRIUS]Engine Speed_7E0(RPM)',
            '[PRIUS]Engine Speed_7E2(RPM)',
            '[PRIUS]FR Wheel Speed(km/h)',
            '[PRIUS]Intake Air Temperature_7E0(°C)',
            '[PRIUS]Intake Air Temperature_7E2(°C)',
            '[PRIUS]Mass Air Flow(gm/sec)',
            '[PRIUS]Vehicle Speed_7B0(km/h)',
            '[PRIUS]Vehicle Speed_7E0(km/h)',
            '[PRIUS]Vehicle Speed_7E2(km/h)',
            'Fuel Trim Bank 1 Short Term(%)',
            'Fuel Trim Bank 1 Long Term(%)',
            'Engine Load(%)',
            'Engine Load(Absolute)(%)',
            'Air Fuel Ratio(Measured)(:1)',
            'Air Fuel Ratio(Commanded)(:1)',
            'ECU(7EA): Ambient air temp(°C)',
            'ECU(7EA): Barometric pressure (from vehicle)(psi)',
            'ECU(7EA): Engine Coolant Temperature(°C)',
            'ECU(7EA): Engine Load(%)',
            'ECU(7EA): Engine RPM(rpm)',
            'ECU(7EA): Intake Air Temperature(°C)',
            'ECU(7EA): Speed (OBD)(km/h)',
            'ECU(7EA): Throttle Position(Manifold)(%)',
            'GPS Satellites',
            'GPS Bearing(°)',
            'GPS Accuracy(m)',
            'O2 Sensor1 Wide Range Current(mA)',
            'O2 Bank 1 Sensor 1 Voltage(V)',
            'O2 Bank 1 Sensor 1 Wide Range Equivalence Ratio(λ)',
            'O2 Bank 1 Sensor 1 Wide Range Voltage(V)',
            'O2 Bank 1 Sensor 2 Voltage(V)',
            'Engine RPM(rpm)',
            'Commanded Equivalence Ratio(lambda)',
            'Mass Air Flow Rate(g/s)',
            'Intake Air Temperature(°C)',
            'Engine Coolant Temperature(°C)',
            'Catalyst Temperature (Bank 1 Sensor 1)(°C)',
            'Catalyst Temperature (Bank 1 Sensor 2)(°C)',
            'GPS vs OBD Speed difference(km/h)',
            'Speed (OBD)(km/h)'
        ];

        $result = $this->mapper->mapCsvHeaders($csvHeaders);
        $stats = $this->mapper->getMappingStats($csvHeaders);

        // Toutes les colonnes devraient être reconnues (pas forcément toutes stockées à cause des doublons)
        $this->assertEquals(63, $stats['total_columns']);
        $this->assertEquals(0, $stats['unmapped_columns'], 'Toutes les colonnes devraient être reconnues');
        $this->assertGreaterThan(0, $stats['duplicate_sources'], 'Il devrait y avoir des doublons détectés');

        // Vérifier quelques colonnes clés
        $this->assertArrayHasKey('timestamp_gps', $result['mapped']);
        $this->assertArrayHasKey('engine_rpm', $result['mapped']);
        $this->assertArrayHasKey('o2_b1s1_voltage', $result['mapped']);
        $this->assertArrayHasKey('o2_b1s2_voltage', $result['mapped']);
        $this->assertArrayHasKey('catalyst_temp_b1s1', $result['mapped']);
        $this->assertArrayHasKey('prius_af_lambda', $result['mapped']);

        // Vérifier que le RPM standard est utilisé (pas les variantes)
        $this->assertEquals('Engine RPM(rpm)', $result['mapped']['engine_rpm']['csv_column']);
    }
}
