<?php

namespace App\DataFixtures;

use App\Entity\OBD2Column;
use App\Entity\OBD2ColumnVariant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture pour initialiser les colonnes OBD2 et leurs variantes
 *
 * Basé sur le mapping actuel dans OBD2ColumnMapper
 * Permet une configuration future via IHM sans modification de code
 */
class OBD2ColumnFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Définition des colonnes avec leurs métadonnées
        $columnsDefinitions = $this->getColumnsDefinitions();

        foreach ($columnsDefinitions as $columnDef) {
            $column = new OBD2Column();
            $column->setName($columnDef['name']);
            $column->setDescription($columnDef['description']);
            $column->setCategory($columnDef['category']);
            $column->setUnit($columnDef['unit'] ?? null);
            $column->setDataType($columnDef['dataType']);
            $column->setMinValue($columnDef['minValue'] ?? null);
            $column->setMaxValue($columnDef['maxValue'] ?? null);
            $column->setErrorValues($columnDef['errorValues'] ?? null);
            $column->setValidationCriteria($columnDef['validationCriteria'] ?? null);
            $column->setActive(true);

            // Ajouter les variantes
            foreach ($columnDef['variants'] as $priority => $variantName) {
                $variant = new OBD2ColumnVariant();
                $variant->setVariantName($variantName);
                $variant->setPriority($priority);
                $variant->setSource('torque_pro');
                $variant->setActive(true);

                $column->addVariant($variant);
            }

            $manager->persist($column);
        }

        $manager->flush();

        echo "✓ " . count($columnsDefinitions) . " colonnes OBD2 créées avec leurs variantes\n";
    }

    private function getColumnsDefinitions(): array
    {
        return [
            // ========== DONNÉES TEMPORELLES ==========
            [
                'name' => 'timestamp_gps',
                'description' => 'Horodatage GPS',
                'category' => 'temporal',
                'unit' => null,
                'dataType' => 'datetime',
                'validationCriteria' => ['type' => 'datetime', 'format' => 'D M d H:i:s e Y'],
                'variants' => [
                    0 => 'GPS Time',
                    1 => 'GPS Timestamp',
                    2 => 'gps_time',
                ],
            ],
            [
                'name' => 'timestamp_device',
                'description' => 'Horodatage appareil',
                'category' => 'temporal',
                'unit' => null,
                'dataType' => 'datetime',
                'validationCriteria' => ['type' => 'datetime'],
                'variants' => [
                    0 => 'Device Time',
                    1 => 'Phone Time',
                    2 => 'System Time',
                    3 => 'device_time',
                ],
            ],

            // ========== GPS/POSITION ==========
            [
                'name' => 'longitude',
                'description' => 'Longitude GPS',
                'category' => 'gps',
                'unit' => '°',
                'dataType' => 'float',
                'minValue' => -180,
                'maxValue' => 180,
                'variants' => [
                    0 => 'Longitude',
                    1 => 'GPS Longitude',
                    2 => 'Lon',
                    3 => 'longitude',
                ],
            ],
            [
                'name' => 'latitude',
                'description' => 'Latitude GPS',
                'category' => 'gps',
                'unit' => '°',
                'dataType' => 'float',
                'minValue' => -90,
                'maxValue' => 90,
                'variants' => [
                    0 => 'Latitude',
                    1 => 'GPS Latitude',
                    2 => 'Lat',
                    3 => 'latitude',
                ],
            ],
            [
                'name' => 'gps_speed_ms',
                'description' => 'Vitesse GPS',
                'category' => 'gps',
                'unit' => 'm/s',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 100,
                'variants' => [
                    0 => 'GPS Speed (Meters/second)',
                    1 => 'GPS Speed(m/s)',
                    2 => 'GPS Speed',
                    3 => 'gps_speed',
                ],
            ],
            [
                'name' => 'gps_altitude',
                'description' => 'Altitude GPS',
                'category' => 'gps',
                'unit' => 'm',
                'dataType' => 'float',
                'minValue' => -500,
                'maxValue' => 9000,
                'variants' => [
                    0 => 'Altitude',
                    1 => 'GPS Altitude',
                    2 => 'Elevation',
                    3 => 'altitude',
                ],
            ],
            [
                'name' => 'gps_bearing',
                'description' => 'Direction GPS',
                'category' => 'gps',
                'unit' => '°',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 360,
                'variants' => [
                    0 => 'Bearing',
                    1 => 'GPS Bearing',
                    2 => 'GPS Bearing(°)',
                    3 => 'Heading',
                ],
            ],
            [
                'name' => 'gps_accuracy',
                'description' => 'Précision GPS',
                'category' => 'gps',
                'unit' => 'm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 100,
                'variants' => [
                    0 => 'GPS Accuracy(m)',
                    1 => 'GPS Accuracy',
                    2 => 'Horizontal Accuracy',
                    3 => 'accuracy',
                ],
            ],
            [
                'name' => 'gps_hdop',
                'description' => 'HDOP GPS',
                'category' => 'gps',
                'unit' => null,
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 50,
                'variants' => [
                    0 => 'Horizontal Dilution of Precision',
                    1 => 'HDOP',
                    2 => 'GPS HDOP',
                    3 => 'hdop',
                ],
            ],
            [
                'name' => 'gps_satellites',
                'description' => 'Nombre de satellites GPS',
                'category' => 'gps',
                'unit' => null,
                'dataType' => 'int',
                'minValue' => 0,
                'maxValue' => 50,
                'variants' => [
                    0 => 'GPS Satellites',
                    1 => 'Satellites',
                    2 => 'Sat Count',
                    3 => 'satellites',
                ],
            ],

            // ========== ACCÉLÉROMÈTRE ==========
            [
                'name' => 'accel_x',
                'description' => 'Accélération axe X',
                'category' => 'accelerometer',
                'unit' => 'g',
                'dataType' => 'float',
                'minValue' => -10,
                'maxValue' => 10,
                'variants' => [
                    0 => 'G(x)',
                    1 => 'Acceleration X',
                    2 => 'Accel X(g)',
                    3 => 'g_x',
                ],
            ],
            [
                'name' => 'accel_y',
                'description' => 'Accélération axe Y',
                'category' => 'accelerometer',
                'unit' => 'g',
                'dataType' => 'float',
                'minValue' => -10,
                'maxValue' => 10,
                'variants' => [
                    0 => 'G(y)',
                    1 => 'Acceleration Y',
                    2 => 'Accel Y(g)',
                    3 => 'g_y',
                ],
            ],
            [
                'name' => 'accel_z',
                'description' => 'Accélération axe Z',
                'category' => 'accelerometer',
                'unit' => 'g',
                'dataType' => 'float',
                'minValue' => -10,
                'maxValue' => 10,
                'variants' => [
                    0 => 'G(z)',
                    1 => 'Acceleration Z',
                    2 => 'Accel Z(g)',
                    3 => 'g_z',
                ],
            ],
            [
                'name' => 'accel_total',
                'description' => 'Accélération totale calibrée',
                'category' => 'accelerometer',
                'unit' => 'g',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10,
                'variants' => [
                    0 => 'G(calibrated)',
                    1 => 'Total G Force',
                    2 => 'G Total',
                    3 => 'g_total',
                ],
            ],

            // ========== SONDES LAMBDA/O2 ==========
            [
                'name' => 'o2_b1s1_voltage',
                'description' => 'Tension sonde O2 amont (Bank 1 Sensor 1)',
                'category' => 'lambda',
                'unit' => 'V',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 1.1,
                'validationCriteria' => ['min_variance' => 0.1, 'check_frozen' => true],
                'variants' => [
                    0 => 'O2 Bank 1 Sensor 1 Voltage(V)',
                    1 => 'O2 Sensor Bank 1 - Sensor 1(V)',
                    2 => 'O2 B1S1(V)',
                    3 => 'O2 Volts Bank 1 sensor 1(V)',
                    4 => 'o2_b1s1_v',
                ],
            ],
            [
                'name' => 'o2_b1s1_voltage_wide',
                'description' => 'Tension sonde O2 wideband amont',
                'category' => 'lambda',
                'unit' => 'V',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 5,
                'variants' => [
                    0 => 'O2 Bank 1 Sensor 1 Wide Range Voltage(V)',
                    1 => 'O2 B1S1 Wide Range(V)',
                    2 => 'Wide Range O2 B1S1(V)',
                    3 => 'o2_b1s1_wide_v',
                ],
            ],
            [
                'name' => 'o2_b1s1_lambda',
                'description' => 'Lambda sonde O2 amont',
                'category' => 'lambda',
                'unit' => 'λ',
                'dataType' => 'float',
                'minValue' => 0.5,
                'maxValue' => 2.0,
                'variants' => [
                    0 => 'O2 Bank 1 Sensor 1 Wide Range Equivalence Ratio(λ)',
                    1 => 'O2 B1S1 Lambda',
                    2 => 'Lambda B1S1',
                    3 => 'Wide Range Lambda B1S1',
                    4 => 'o2_b1s1_lambda',
                ],
            ],
            [
                'name' => 'o2_b1s1_current',
                'description' => 'Courant sonde O2 wideband amont',
                'category' => 'lambda',
                'unit' => 'mA',
                'dataType' => 'float',
                'minValue' => -20,
                'maxValue' => 20,
                'variants' => [
                    0 => 'O2 Sensor1 Wide Range Current(mA)',
                    1 => 'O2 B1S1 Current(mA)',
                    2 => 'Wide Range O2 Current B1S1(mA)',
                    3 => 'o2_b1s1_ma',
                ],
            ],
            [
                'name' => 'o2_b1s2_voltage',
                'description' => 'Tension sonde O2 aval (Bank 1 Sensor 2)',
                'category' => 'lambda',
                'unit' => 'V',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 1.1,
                'validationCriteria' => ['max_variance' => 0.3, 'expected_stable' => true],
                'variants' => [
                    0 => 'O2 Bank 1 Sensor 2 Voltage(V)',
                    1 => 'O2 Sensor Bank 1 - Sensor 2(V)',
                    2 => 'O2 B1S2(V)',
                    3 => 'O2 Volts Bank 1 sensor 2(V)',
                    4 => 'o2_b1s2_v',
                ],
            ],

            // ========== AIR/FUEL RATIO ==========
            [
                'name' => 'afr_measured',
                'description' => 'Ratio air/carburant mesuré',
                'category' => 'fuel',
                'unit' => ':1',
                'dataType' => 'float',
                'minValue' => 10,
                'maxValue' => 20,
                'variants' => [
                    0 => 'Air Fuel Ratio(Measured)(:1)',
                    1 => 'AFR Measured(:1)',
                    2 => 'Measured AFR',
                    3 => 'Air/Fuel Ratio (Measured)',
                    4 => 'afr_measured',
                ],
            ],
            [
                'name' => 'afr_commanded',
                'description' => 'Ratio air/carburant commandé',
                'category' => 'fuel',
                'unit' => ':1',
                'dataType' => 'float',
                'minValue' => 10,
                'maxValue' => 20,
                'variants' => [
                    0 => 'Air Fuel Ratio(Commanded)(:1)',
                    1 => 'AFR Commanded(:1)',
                    2 => 'Commanded AFR',
                    3 => 'Air/Fuel Ratio (Commanded)',
                    4 => 'afr_commanded',
                ],
            ],
            [
                'name' => 'lambda_commanded',
                'description' => 'Lambda commandé',
                'category' => 'fuel',
                'unit' => 'λ',
                'dataType' => 'float',
                'minValue' => 0.5,
                'maxValue' => 2.0,
                'variants' => [
                    0 => 'Commanded Equivalence Ratio(lambda)',
                    1 => 'Commanded Lambda',
                    2 => 'Lambda Commanded',
                    3 => 'commanded_lambda',
                ],
            ],
            [
                'name' => 'stft_b1',
                'description' => 'Correction carburant court terme Bank 1',
                'category' => 'fuel',
                'unit' => '%',
                'dataType' => 'float',
                'minValue' => -50,
                'maxValue' => 50,
                'variants' => [
                    0 => 'Fuel Trim Bank 1 Short Term(%)',
                    1 => 'Short Term Fuel Trim Bank 1(%)',
                    2 => 'STFT B1(%)',
                    3 => 'Short term fuel trim—Bank 1(%)',
                    4 => 'stft_b1',
                ],
            ],
            [
                'name' => 'ltft_b1',
                'description' => 'Correction carburant long terme Bank 1',
                'category' => 'fuel',
                'unit' => '%',
                'dataType' => 'float',
                'minValue' => -50,
                'maxValue' => 50,
                'variants' => [
                    0 => 'Fuel Trim Bank 1 Long Term(%)',
                    1 => 'Long Term Fuel Trim Bank 1(%)',
                    2 => 'LTFT B1(%)',
                    3 => 'Long term fuel trim—Bank 1(%)',
                    4 => 'ltft_b1',
                ],
            ],

            // ========== SPÉCIFIQUE PRIUS ==========
            [
                'name' => 'prius_af_lambda',
                'description' => 'Lambda Air/Fuel Prius',
                'category' => 'prius',
                'unit' => 'λ',
                'dataType' => 'float',
                'minValue' => 0.5,
                'maxValue' => 2.0,
                'variants' => [
                    0 => '[PRIUS]AF Lambda B1S1',
                    1 => 'PRIUS AF Lambda',
                    2 => 'Prius Lambda B1S1',
                    3 => 'prius_lambda',
                ],
            ],
            [
                'name' => 'prius_afs_voltage',
                'description' => 'Tension AFS Prius',
                'category' => 'prius',
                'unit' => 'V',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 5,
                'variants' => [
                    0 => '[PRIUS]AFS Voltage B1S1(V)',
                    1 => 'PRIUS AFS Voltage(V)',
                    2 => 'Prius AFS B1S1(V)',
                    3 => 'prius_afs_v',
                ],
            ],
            [
                'name' => 'prius_misfire_count',
                'description' => 'Nombre de ratés Prius (tous cylindres)',
                'category' => 'prius',
                'unit' => null,
                'dataType' => 'int',
                'minValue' => 0,
                'maxValue' => 10000,
                'variants' => [
                    0 => '[PRIUS]All Cylinders Misfire Count',
                    1 => 'PRIUS Misfire Count',
                    2 => 'Prius Total Misfires',
                    3 => 'prius_misfires',
                ],
            ],
            [
                'name' => 'prius_maf',
                'description' => 'Débit air massique Prius',
                'category' => 'prius',
                'unit' => 'g/s',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 300,
                'variants' => [
                    0 => '[PRIUS]Mass Air Flow(gm/sec)',
                    1 => 'PRIUS MAF(g/s)',
                    2 => 'Prius MAF',
                    3 => 'prius_maf',
                ],
            ],

            // ========== TEMPÉRATURES ==========
            [
                'name' => 'coolant_temp',
                'description' => 'Température liquide de refroidissement moteur',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 150,
                'errorValues' => [65535, -1, 255],
                'validationCriteria' => ['min_non_zero_percent' => 50],
                'variants' => [
                    0 => 'Engine Coolant Temperature(°C)',
                    1 => 'ECU(7EA): Engine Coolant Temperature(°C)',
                    2 => 'Coolant Temperature(°C)',
                    3 => 'Engine Coolant Temp(°C)',
                    4 => 'Coolant Temp(°C)',
                    5 => 'ECT(°C)',
                    6 => 'coolant_temp_c',
                ],
            ],
            [
                'name' => 'prius_coolant_7c0',
                'description' => 'Température liquide de refroidissement Prius (ECU 7C0)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 150,
                'variants' => [
                    0 => '[PRIUS]Engine Coolant Temperature_7C0(°C)',
                    1 => '[PRIUS]Coolant Temperature_7C0(°C)',
                    2 => 'ECU(7C0): Engine Coolant Temperature(°C)',
                    3 => 'PRIUS Coolant 7C0(°C)',
                ],
            ],
            [
                'name' => 'prius_coolant_7e0',
                'description' => 'Température liquide de refroidissement Prius (ECU 7E0)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 150,
                'variants' => [
                    0 => '[PRIUS]Engine Coolant Temperature_7E0(°C)',
                    1 => '[PRIUS]Coolant Temperature_7E0(°C)',
                    2 => 'ECU(7E0): Engine Coolant Temperature(°C)',
                    3 => 'PRIUS Coolant 7E0(°C)',
                ],
            ],
            [
                'name' => 'prius_coolant_7c4',
                'description' => 'Température liquide de refroidissement Prius (ECU 7C4)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 150,
                'variants' => [
                    0 => '[PRIUS]Engine Coolant Temperature_7C4(°C)',
                    1 => '[PRIUS]Engine Coolant Temp_7C4(°C)',
                    2 => 'ECU(7C4): Engine Coolant Temperature(°C)',
                    3 => 'PRIUS Coolant 7C4(°C)',
                ],
            ],
            [
                'name' => 'prius_coolant_7e2',
                'description' => 'Température liquide de refroidissement Prius (ECU 7E2)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 150,
                'variants' => [
                    0 => '[PRIUS]Engine Coolant Temperature_7E2(°C)',
                    1 => 'ECU(7E2): Engine Coolant Temperature(°C)',
                    2 => 'PRIUS Coolant 7E2(°C)',
                ],
            ],
            [
                'name' => 'intake_air_temp',
                'description' => 'Température air admission',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 100,
                'variants' => [
                    0 => 'Intake Air Temperature(°C)',
                    1 => 'ECU(7EA): Intake Air Temperature(°C)',
                    2 => 'IAT(°C)',
                    3 => 'Intake Air Temp(°C)',
                    4 => 'Air Intake Temperature(°C)',
                    5 => 'intake_temp_c',
                ],
            ],
            [
                'name' => 'prius_iat_7e0',
                'description' => 'Température air admission Prius (ECU 7E0)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 100,
                'variants' => [
                    0 => '[PRIUS]Intake Air Temperature_7E0(°C)',
                    1 => 'ECU(7E0): Intake Air Temperature(°C)',
                    2 => 'PRIUS IAT 7E0(°C)',
                ],
            ],
            [
                'name' => 'prius_iat_7e2',
                'description' => 'Température air admission Prius (ECU 7E2)',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 100,
                'variants' => [
                    0 => '[PRIUS]Intake Air Temperature_7E2(°C)',
                    1 => 'ECU(7E2): Intake Air Temperature(°C)',
                    2 => 'PRIUS IAT 7E2(°C)',
                ],
            ],
            [
                'name' => 'ambient_temp',
                'description' => 'Température ambiante',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => -40,
                'maxValue' => 60,
                'variants' => [
                    0 => 'Ambient air temp(°C)',
                    1 => 'ECU(7EA): Ambient air temp(°C)',
                    2 => 'Ambient Air Temperature(°C)',
                    3 => 'Outside Temperature(°C)',
                    4 => 'Ambient Temp(°C)',
                    5 => 'ambient_temp_c',
                ],
            ],
            [
                'name' => 'catalyst_temp_b1s1',
                'description' => 'Température catalyseur Bank 1 Sensor 1',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 1000,
                'variants' => [
                    0 => 'Catalyst Temperature (Bank 1 Sensor 1)(°C)',
                    1 => 'Cat Temp B1S1(°C)',
                    2 => 'Catalyst Temp B1S1',
                    3 => 'cat_temp_b1s1',
                ],
            ],
            [
                'name' => 'catalyst_temp_b1s2',
                'description' => 'Température catalyseur Bank 1 Sensor 2',
                'category' => 'temperature',
                'unit' => '°C',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 1000,
                'variants' => [
                    0 => 'Catalyst Temperature (Bank 1 Sensor 2)(°C)',
                    1 => 'Cat Temp B1S2(°C)',
                    2 => 'Catalyst Temp B1S2',
                    3 => 'cat_temp_b1s2',
                ],
            ],

            // ========== RÉGIME MOTEUR ==========
            [
                'name' => 'engine_rpm',
                'description' => 'Régime moteur',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199, 65535, -1],
                'validationCriteria' => ['allow_zeros' => true],
                'variants' => [
                    0 => 'Engine RPM(rpm)',
                    1 => 'ECU(7EA): Engine RPM(rpm)',
                    2 => 'Engine Speed(rpm)',
                    3 => 'RPM',
                    4 => 'Engine RPM',
                    5 => 'rpm',
                ],
            ],
            [
                'name' => 'prius_rpm_7e0',
                'description' => 'Régime moteur Prius (ECU 7E0)',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Engine Speed_7E0(RPM)',
                    1 => 'ECU(7E0): Engine Speed(rpm)',
                    2 => 'PRIUS RPM 7E0',
                ],
            ],
            [
                'name' => 'prius_rpm_7e2',
                'description' => 'Régime moteur Prius (ECU 7E2)',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Engine Speed_7E2(RPM)',
                    1 => 'ECU(7E2): Engine Speed(rpm)',
                    2 => 'PRIUS RPM 7E2',
                ],
            ],
            [
                'name' => 'prius_rpm_cyl1',
                'description' => 'Régime cylindre 1 Prius',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Cylinder 1 RPM',
                    1 => '[PRIUS]Engine Speed of Cyl #1 (51199 rpm: Active Test not performed)(RPM)',
                    2 => 'PRIUS Cyl 1 RPM',
                    3 => 'Prius Cylinder 1 Speed',
                ],
            ],
            [
                'name' => 'prius_rpm_cyl2',
                'description' => 'Régime cylindre 2 Prius',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Cylinder 2 RPM',
                    1 => '[PRIUS]Engine Speed of Cyl #2 (51199 rpm: Active Test not performed)(RPM)',
                    2 => 'PRIUS Cyl 2 RPM',
                    3 => 'Prius Cylinder 2 Speed',
                ],
            ],
            [
                'name' => 'prius_rpm_cyl3',
                'description' => 'Régime cylindre 3 Prius',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Cylinder 3 RPM',
                    1 => '[PRIUS]Engine Speed of Cyl #3 (51199 rpm: Active Test not performed)(RPM)',
                    2 => 'PRIUS Cyl 3 RPM',
                    3 => 'Prius Cylinder 3 Speed',
                ],
            ],
            [
                'name' => 'prius_rpm_cyl4',
                'description' => 'Régime cylindre 4 Prius',
                'category' => 'engine',
                'unit' => 'rpm',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 10000,
                'errorValues' => [51199],
                'variants' => [
                    0 => '[PRIUS]Cylinder 4 RPM',
                    1 => '[PRIUS]Engine Speed of Cyl #4 (51199 rpm: Active Test not performed)(RPM)',
                    2 => 'PRIUS Cyl 4 RPM',
                    3 => 'Prius Cylinder 4 Speed',
                ],
            ],

            // ========== VITESSE ==========
            [
                'name' => 'vehicle_speed',
                'description' => 'Vitesse véhicule',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 250,
                'validationCriteria' => ['allow_zeros' => true],
                'variants' => [
                    0 => 'Speed (OBD)(km/h)',
                    1 => 'ECU(7EA): Speed (OBD)(km/h)',
                    2 => 'Vehicle Speed(km/h)',
                    3 => 'Speed',
                    4 => 'VSS(km/h)',
                    5 => 'vehicle_speed_kmh',
                ],
            ],
            [
                'name' => 'prius_speed_7b0',
                'description' => 'Vitesse Prius (ECU 7B0)',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 250,
                'variants' => [
                    0 => '[PRIUS]Vehicle Speed_7B0(km/h)',
                    1 => 'ECU(7B0): Vehicle Speed(km/h)',
                    2 => 'PRIUS Speed 7B0',
                ],
            ],
            [
                'name' => 'prius_speed_7e0',
                'description' => 'Vitesse Prius (ECU 7E0)',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 250,
                'variants' => [
                    0 => '[PRIUS]Vehicle Speed_7E0(km/h)',
                    1 => 'ECU(7E0): Vehicle Speed(km/h)',
                    2 => 'PRIUS Speed 7E0',
                ],
            ],
            [
                'name' => 'prius_speed_7e2',
                'description' => 'Vitesse Prius (ECU 7E2)',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 250,
                'variants' => [
                    0 => '[PRIUS]Vehicle Speed_7E2(km/h)',
                    1 => 'ECU(7E2): Vehicle Speed(km/h)',
                    2 => 'PRIUS Speed 7E2',
                ],
            ],
            [
                'name' => 'prius_wheel_speed_fr',
                'description' => 'Vitesse roue avant droite Prius',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 250,
                'variants' => [
                    0 => '[PRIUS]Wheel Speed Front Right(km/h)',
                    1 => '[PRIUS]FR Wheel Speed(km/h)',
                    2 => 'PRIUS FR Wheel Speed',
                    3 => 'Prius Front Right Wheel',
                ],
            ],
            [
                'name' => 'speed_difference',
                'description' => 'Différence vitesse GPS vs OBD',
                'category' => 'speed',
                'unit' => 'km/h',
                'dataType' => 'float',
                'minValue' => -50,
                'maxValue' => 50,
                'variants' => [
                    0 => 'GPS vs OBD Speed difference(km/h)',
                    1 => 'Speed difference(km/h)',
                    2 => 'Speed Diff GPS-OBD',
                    3 => 'speed_diff',
                ],
            ],

            // ========== CHARGE/AIR ==========
            [
                'name' => 'engine_load',
                'description' => 'Charge moteur calculée',
                'category' => 'load',
                'unit' => '%',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 100,
                'variants' => [
                    0 => 'Engine Load(%)',
                    1 => 'ECU(7EA): Engine Load(%)',
                    2 => 'Calculated Engine Load(%)',
                    3 => 'Load(%)',
                    4 => 'engine_load_pct',
                ],
            ],
            [
                'name' => 'engine_load_absolute',
                'description' => 'Charge moteur absolue',
                'category' => 'load',
                'unit' => '%',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 100,
                'variants' => [
                    0 => 'Engine Load(Absolute)(%)',
                    1 => 'Absolute Load Value(%)',
                    2 => 'Absolute Engine Load',
                    3 => 'engine_load_abs',
                ],
            ],
            [
                'name' => 'throttle_position',
                'description' => 'Position papillon',
                'category' => 'load',
                'unit' => '%',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 100,
                'variants' => [
                    0 => 'Throttle Position(Manifold)(%)',
                    1 => 'ECU(7EA): Throttle Position(Manifold)(%)',
                    2 => 'Throttle Position(%)',
                    3 => 'TPS(%)',
                    4 => 'Throttle Pos(%)',
                    5 => 'throttle_pct',
                ],
            ],
            [
                'name' => 'maf_rate',
                'description' => 'Débit air massique',
                'category' => 'load',
                'unit' => 'g/s',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 300,
                'variants' => [
                    0 => 'Mass Air Flow Rate(g/s)',
                    1 => 'MAF(g/s)',
                    2 => 'Air Flow Rate',
                    3 => 'maf',
                ],
            ],
            [
                'name' => 'barometric_pressure',
                'description' => 'Pression atmosphérique',
                'category' => 'load',
                'unit' => 'kPa',
                'dataType' => 'float',
                'minValue' => 80,
                'maxValue' => 110,
                'variants' => [
                    0 => 'Barometric pressure (from vehicle)(kPa)',
                    1 => 'ECU(7EA): Barometric pressure (from vehicle)(psi)',
                    2 => 'Barometric Pressure(kPa)',
                    3 => 'Barometric Pressure(psi)',
                    4 => 'BARO(kPa)',
                    5 => 'Baro Pressure(psi)',
                    6 => 'baro_pressure',
                ],
            ],
            [
                'name' => 'manifold_pressure',
                'description' => 'Pression collecteur admission',
                'category' => 'load',
                'unit' => 'kPa',
                'dataType' => 'float',
                'minValue' => 0,
                'maxValue' => 255,
                'variants' => [
                    0 => 'Intake Manifold Pressure(kPa)',
                    1 => 'MAP(kPa)',
                    2 => 'Manifold Pressure',
                    3 => 'map_pressure',
                ],
            ],
        ];
    }
}
