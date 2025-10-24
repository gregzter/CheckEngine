<?php

namespace App\Service;

/**
 * Service de détection des diagnostics possibles
 *
 * Analyse les colonnes disponibles ET leur contenu pour déterminer:
 * - Quels diagnostics sont réalisables
 * - Quelle source de données utiliser en cas de doublon
 * - Le taux de complétude des données
 */
class DiagnosticDetector
{
    /**
     * Définition des colonnes requises pour chaque diagnostic
     */
    private const DIAGNOSTIC_REQUIREMENTS = [
        'catalyst' => [
            'mandatory' => ['o2_b1s1_voltage', 'o2_b1s2_voltage', 'engine_rpm'],
            'recommended' => ['catalyst_temp_b1s1', 'catalyst_temp_b1s2', 'vehicle_speed', 'engine_load', 'coolant_temp'],
            'optional' => ['stft_b1', 'ltft_b1', 'maf_rate']
        ],
        'o2_sensors' => [
            'mandatory' => ['o2_b1s1_voltage', 'o2_b1s2_voltage', 'engine_rpm'],
            'recommended' => ['o2_b1s1_lambda', 'stft_b1', 'ltft_b1', 'afr_measured', 'afr_commanded', 'prius_af_lambda', 'prius_afs_voltage'],
            'optional' => ['o2_b1s1_current', 'lambda_commanded']
        ],
        'engine' => [
            'mandatory' => ['engine_rpm', 'engine_load'],
            'recommended' => ['prius_misfire_count', 'maf_rate', 'coolant_temp', 'stft_b1', 'ltft_b1', 'throttle_position'],
            'optional' => ['intake_air_temp', 'barometric_pressure']
        ],
        'driving' => [
            'mandatory' => ['vehicle_speed', 'timestamp_device'],
            'recommended' => ['engine_load', 'accel_x', 'accel_y', 'accel_z', 'engine_rpm'],
            'optional' => ['gps_speed_ms', 'throttle_position']
        ],
        'hybrid' => [
            'mandatory' => ['engine_rpm', 'vehicle_speed'],
            'recommended' => ['prius_af_lambda', 'prius_afs_voltage', 'maf_rate'],
            'optional' => ['prius_rpm_7e0', 'prius_rpm_7e2']
        ]
    ];

    /**
     * Valeurs d'erreur connues dans les logs OBD2
     */
    private const ERROR_VALUES = [
        51199,   // Prius: valeur d'erreur pour RPM cylindres
        65535,   // 0xFFFF: valeur max unsigned int (erreur générique)
        -1,      // Valeur d'erreur signée
        255,     // 0xFF: erreur sur 8 bits
        32767,   // 0x7FFF: max signed int 16 bits
        -32768,  // Min signed int 16 bits
    ];

    /**
     * Vérifie si une valeur est une valeur d'erreur connue
     */
    private function isErrorValue(float $value): bool
    {
        foreach (self::ERROR_VALUES as $errorValue) {
            if (abs($value - $errorValue) < 0.01) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valide qu'une colonne contient des données utiles
     *
     * @param array $columnData Tableau de valeurs de la colonne
     * @param string $columnName Nom de la colonne (pour validation spécifique)
     * @return array ['valid' => bool, 'stats' => [...]]
     */
    public function validateColumnData(array $columnData, string $columnName): array
    {
        $totalRows = count($columnData);

        if ($totalRows === 0) {
            return ['valid' => false, 'reason' => 'empty_array', 'stats' => []];
        }

        $emptyCount = 0;
        $zeroCount = 0;
        $nullCount = 0;
        $invalidCount = 0;
        $errorValueCount = 0;
        $values = [];

        foreach ($columnData as $value) {
            // Nettoyer la valeur
            $value = is_string($value) ? trim($value) : $value;

            // Compter les valeurs vides/nulles
            if ($value === '' || $value === '-' || $value === 'N/A' || $value === 'NA') {
                $emptyCount++;
                continue;
            }

            if ($value === null || strtolower((string)$value) === 'null') {
                $nullCount++;
                continue;
            }

            // Convertir en float pour analyse
            $numValue = is_numeric($value) ? (float)$value : null;

            if ($numValue === null) {
                $invalidCount++;
                continue;
            }

            // Vérifier les valeurs d'erreur
            if ($this->isErrorValue($numValue)) {
                $errorValueCount++;
                continue;
            }

            if ($numValue == 0) {
                $zeroCount++;
            }

            $values[] = $numValue;
        }

        $validCount = count($values);
        $validRate = $totalRows > 0 ? ($validCount / $totalRows) * 100 : 0;

        // Calcul des statistiques sur les valeurs valides
        $stats = [
            'total_rows' => $totalRows,
            'valid_count' => $validCount,
            'empty_count' => $emptyCount,
            'null_count' => $nullCount,
            'zero_count' => $zeroCount,
            'invalid_count' => $invalidCount,
            'error_value_count' => $errorValueCount,
            'valid_rate' => round($validRate, 2),
            'min' => $validCount > 0 ? min($values) : null,
            'max' => $validCount > 0 ? max($values) : null,
            'avg' => $validCount > 0 ? round(array_sum($values) / $validCount, 2) : null,
            'median' => $validCount > 0 ? $this->calculateMedian($values) : null,
            'std_dev' => $validCount > 1 ? $this->calculateStdDev($values) : null,
            'non_zero_count' => $validCount - $zeroCount,
            'variance' => $validCount > 0 ? round(max($values) - min($values), 2) : 0
        ];

        // Critères de validation
        $isValid = $this->isColumnValid($stats, $columnName);

        return [
            'valid' => $isValid,
            'reason' => $this->getValidationReason($stats, $columnName),
            'stats' => $stats
        ];
    }

    /**
     * Calcule la médiane d'un tableau de valeurs
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calcule l'écart-type d'un tableau de valeurs
     */
    private function calculateStdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return round(sqrt($variance / $count), 2);
    }

    /**
     * Détermine si une colonne est valide selon des critères spécifiques
     */
    private function isColumnValid(array $stats, string $columnName): bool
    {
        // Au moins 30% de données valides requises
        if ($stats['valid_rate'] < 30) {
            return false;
        }

        // Cas spécifique: RPM - doit avoir au moins 10% de valeurs non-nulles
        if (str_contains($columnName, 'rpm') || str_contains($columnName, 'speed')) {
            // Le moteur peut être arrêté, donc on accepte des zéros
            // Mais au moins 10% des données doivent être non-nulles
            return $stats['valid_rate'] >= 10;
        }

        // Cas spécifique: Températures - ne devraient pas être toutes à 0
        if (str_contains($columnName, 'temp')) {
            $nonZeroRate = $stats['valid_count'] > 0
                ? ($stats['non_zero_count'] / $stats['valid_count']) * 100
                : 0;
            return $nonZeroRate >= 50; // Au moins 50% des valeurs non-nulles
        }

        // Cas spécifique: Sondes O2/Lambda - doivent avoir des variations
        if (str_contains($columnName, 'o2_') || str_contains($columnName, 'lambda') || str_contains($columnName, 'afr')) {
            if ($stats['min'] === $stats['max']) {
                return false; // Valeur figée = sonde défectueuse
            }
            $nonZeroRate = $stats['valid_count'] > 0
                ? ($stats['non_zero_count'] / $stats['valid_count']) * 100
                : 0;
            return $nonZeroRate >= 30;
        }

        // Cas spécifique: GPS - au moins quelques points valides
        if (str_contains($columnName, 'gps_') || str_contains($columnName, 'latitude') || str_contains($columnName, 'longitude')) {
            return $stats['valid_rate'] >= 20;
        }

        // Par défaut: au moins 30% de données valides
        return $stats['valid_rate'] >= 30;
    }

    /**
     * Retourne la raison de validation/invalidation
     */
    private function getValidationReason(array $stats, string $columnName): string
    {
        if ($stats['valid_rate'] < 30) {
            return sprintf('insufficient_data (%.1f%% valid)', $stats['valid_rate']);
        }

        if (str_contains($columnName, 'temp')) {
            $nonZeroRate = $stats['valid_count'] > 0
                ? ($stats['non_zero_count'] / $stats['valid_count']) * 100
                : 0;
            if ($nonZeroRate < 50) {
                return sprintf('all_zeros_temp (%.1f%% non-zero)', $nonZeroRate);
            }
        }

        if (str_contains($columnName, 'o2_') || str_contains($columnName, 'lambda')) {
            if ($stats['min'] === $stats['max']) {
                return sprintf('frozen_value (%.2f)', $stats['min'] ?? 0);
            }
        }

        return 'valid';
    }

    /**
     * Calcule la corrélation entre deux colonnes de données
     * Retourne un score de 0 (totalement différent) à 100 (identique)
     */
    public function calculateColumnCorrelation(array $data1, array $data2): float
    {
        $minLength = min(count($data1), count($data2));

        if ($minLength === 0) {
            return 0.0;
        }

        // Prendre le minimum des deux longueurs
        $data1 = array_slice($data1, 0, $minLength);
        $data2 = array_slice($data2, 0, $minLength);

        $identicalCount = 0;
        $veryCloseCount = 0;
        $closeCount = 0;
        $validPairs = 0;

        for ($i = 0; $i < $minLength; $i++) {
            $val1 = is_numeric($data1[$i]) ? (float)$data1[$i] : null;
            $val2 = is_numeric($data2[$i]) ? (float)$data2[$i] : null;

            // Ignorer les paires invalides
            if ($val1 === null || $val2 === null) {
                continue;
            }

            $validPairs++;

            $diff = abs($val1 - $val2);
            $avgValue = ($val1 + $val2) / 2;

            // Valeurs identiques (< 0.1% différence ou < 0.01 absolu)
            if ($diff < 0.01 || ($avgValue != 0 && $diff / $avgValue < 0.001)) {
                $identicalCount++;
                $veryCloseCount++;
                $closeCount++;
            }
            // Valeurs très proches (< 2% de différence)
            elseif ($avgValue != 0 && $diff / $avgValue < 0.02) {
                $veryCloseCount++;
                $closeCount++;
            }
            // Valeurs proches (< 10% de différence)
            elseif ($avgValue != 0 && $diff / $avgValue < 0.10) {
                $closeCount++;
            }
        }

        if ($validPairs === 0) {
            return 0.0;
        }

        // Score pondéré: identiques (50%) + très proches (30%) + proches (20%)
        $identicalScore = ($identicalCount / $validPairs) * 50;
        $veryCloseScore = ($veryCloseCount / $validPairs) * 30;
        $closeScore = ($closeCount / $validPairs) * 20;

        return round($identicalScore + $veryCloseScore + $closeScore, 2);
    }

    /**
     * Sélectionne la meilleure colonne parmi des doublons
     *
     * @param array $duplicates Tableau des doublons avec leurs données
     *                          Format: [['db_name' => 'x', 'priority' => 0, 'data' => [...]], ...]
     * @param bool $verifyCorrelation Si true, vérifie que les doublons sont vraiment corrélés
     * @return array|null La meilleure colonne ou null si aucune n'est valide
     */
    public function selectBestColumn(array $duplicates, bool $verifyCorrelation = true): ?array
    {
        // Si vérification de corrélation activée et plusieurs sources
        if ($verifyCorrelation && count($duplicates) > 1) {
            // Vérifier que les colonnes sont réellement corrélées
            $reference = $duplicates[0]['data'];
            $correlations = [];

            foreach ($duplicates as $idx => $duplicate) {
                if ($idx === 0) {
                    $correlations[$idx] = 100.0; // Référence à elle-même
                } else {
                    $correlations[$idx] = $this->calculateColumnCorrelation($reference, $duplicate['data']);
                }
            }

            // Filtrer les colonnes avec corrélation faible (< 80% = pas vraiment des doublons)
            $correlatedDuplicates = [];
            foreach ($duplicates as $idx => $duplicate) {
                if ($correlations[$idx] >= 80.0) {
                    $duplicate['correlation'] = $correlations[$idx];
                    $correlatedDuplicates[] = $duplicate;
                }
            }

            // Si aucune corrélation forte, ce ne sont pas de vrais doublons
            // On garde toutes les colonnes comme sources indépendantes
            if (count($correlatedDuplicates) < 2) {
                $duplicates = $duplicates; // Garder toutes
            } else {
                $duplicates = $correlatedDuplicates;
            }
        }

        $validColumns = [];

        foreach ($duplicates as $column) {
            $validation = $this->validateColumnData($column['data'], $column['db_name']);

            if ($validation['valid']) {
                // Score composite: priorité (40%) + taux de validité (40%) + corrélation (20%)
                $priorityScore = (10 - min($column['priority'], 10)) * 4; // Max 40 points
                $validityScore = $validation['stats']['valid_rate'] * 0.4; // Max 40 points
                $correlationScore = isset($column['correlation']) ? $column['correlation'] * 0.2 : 20; // Max 20 points

                $totalScore = $priorityScore + $validityScore + $correlationScore;

                $validColumns[] = [
                    'column' => $column,
                    'validation' => $validation,
                    'score' => $totalScore
                ];
            }
        }

        // Aucune colonne valide trouvée
        if (empty($validColumns)) {
            return null;
        }

        // Trier par score décroissant
        usort($validColumns, fn($a, $b) => $b['score'] <=> $a['score']);

        return $validColumns[0]['column'];
    }

    /**
     * Détecte quels diagnostics sont possibles avec les colonnes disponibles
     *
     * @param array $availableColumns Colonnes disponibles avec leurs données
     *                                Format: ['db_name' => ['data' => [...], 'validation' => [...]]]
     * @return array Résultat par diagnostic
     */
    public function detectAvailableDiagnostics(array $availableColumns): array
    {
        $results = [];

        foreach (self::DIAGNOSTIC_REQUIREMENTS as $diagnosticType => $requirements) {
            $result = $this->checkDiagnosticFeasibility($diagnosticType, $requirements, $availableColumns);
            $results[$diagnosticType] = $result;
        }

        return $results;
    }

    /**
     * Vérifie la faisabilité d'un diagnostic
     */
    private function checkDiagnosticFeasibility(string $diagnosticType, array $requirements, array $availableColumns): array
    {
        $missingMandatory = [];
        $missingRecommended = [];
        $availableMandatory = [];
        $availableRecommended = [];
        $availableOptional = [];

        // Vérifier colonnes obligatoires
        foreach ($requirements['mandatory'] as $column) {
            if (isset($availableColumns[$column]) && $availableColumns[$column]['validation']['valid']) {
                $availableMandatory[] = $column;
            } else {
                $missingMandatory[] = $column;
            }
        }

        // Vérifier colonnes recommandées
        foreach ($requirements['recommended'] as $column) {
            if (isset($availableColumns[$column]) && $availableColumns[$column]['validation']['valid']) {
                $availableRecommended[] = $column;
            } else {
                $missingRecommended[] = $column;
            }
        }

        // Vérifier colonnes optionnelles
        foreach ($requirements['optional'] as $column) {
            if (isset($availableColumns[$column]) && $availableColumns[$column]['validation']['valid']) {
                $availableOptional[] = $column;
            }
        }

        // Calculer le taux de complétude
        $totalMandatory = count($requirements['mandatory']);
        $totalRecommended = count($requirements['recommended']);
        $totalOptional = count($requirements['optional']);

        $mandatoryScore = $totalMandatory > 0 ? (count($availableMandatory) / $totalMandatory) * 100 : 100;
        $recommendedScore = $totalRecommended > 0 ? (count($availableRecommended) / $totalRecommended) * 100 : 100;
        $optionalScore = $totalOptional > 0 ? (count($availableOptional) / $totalOptional) * 100 : 100;

        // Score global pondéré: 70% mandatory, 20% recommended, 10% optional
        $completeness = ($mandatoryScore * 0.7) + ($recommendedScore * 0.2) + ($optionalScore * 0.1);

        // Diagnostic disponible si toutes les colonnes obligatoires sont présentes ET valides
        $available = empty($missingMandatory);

        return [
            'available' => $available,
            'completeness' => round($completeness, 2),
            'mandatory_score' => round($mandatoryScore, 2),
            'recommended_score' => round($recommendedScore, 2),
            'optional_score' => round($optionalScore, 2),
            'available_mandatory' => $availableMandatory,
            'available_recommended' => $availableRecommended,
            'available_optional' => $availableOptional,
            'missing_mandatory' => $missingMandatory,
            'missing_recommended' => $missingRecommended,
            'confidence' => $this->calculateConfidence($completeness, count($availableMandatory), count($availableRecommended))
        ];
    }

    /**
     * Calcule un niveau de confiance pour le diagnostic
     */
    private function calculateConfidence(float $completeness, int $mandatoryCount, int $recommendedCount): string
    {
        if ($completeness >= 90 && $recommendedCount >= 3) {
            return 'high';
        } elseif ($completeness >= 70 && $mandatoryCount >= 2) {
            return 'medium';
        } elseif ($completeness >= 50) {
            return 'low';
        } else {
            return 'insufficient';
        }
    }

    /**
     * Retourne les exigences pour un diagnostic spécifique
     */
    public function getDiagnosticRequirements(string $diagnosticType): ?array
    {
        return self::DIAGNOSTIC_REQUIREMENTS[$diagnosticType] ?? null;
    }

    /**
     * Liste tous les types de diagnostics disponibles
     */
    public function getAllDiagnosticTypes(): array
    {
        return array_keys(self::DIAGNOSTIC_REQUIREMENTS);
    }
}
