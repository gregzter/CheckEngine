<?php

namespace App\Service;

use App\Repository\OBD2ColumnVariantRepository;

/**
 * Service de mapping et normalisation des colonnes OBD2
 *
 * Version 2.0: Utilise la base de données pour le mapping (au lieu d'arrays hardcodés)
 *
 * Gère:
 * - La normalisation des noms de colonnes vers des noms standards BDD
 * - La détection automatique des colonnes disponibles
 * - La gestion des doublons (plusieurs sources pour la même donnée)
 * - La sélection de la meilleure source en cas de doublon
 *
 * Avantages de la version DB:
 * - Configuration modifiable sans redéploiement
 * - Support de nouvelles sources OBD2 via UI admin (futur)
 * - Historique des modifications
 * - Export/import de configurations
 */
class OBD2ColumnMapper
{
    /**
     * Cache en mémoire du mapping: nom_normalisé => [['variant' => '...', 'priority' => 0], ...]
     */
    private array $columnMapping = [];

    /**
     * Reverse lookup cache: variant_name => nom_normalisé
     */
    private array $reverseMapping = [];

    public function __construct(
        private readonly OBD2ColumnVariantRepository $variantRepository
    ) {
        $this->loadMappingFromDatabase();
    }

    /**
     * Charge le mapping depuis la base de données (appelé une seule fois au démarrage)
     */
    private function loadMappingFromDatabase(): void
    {
        $mappings = $this->variantRepository->getAllActiveMappings();

        foreach ($mappings as $normalizedName => $variants) {
            $this->columnMapping[$normalizedName] = $variants;

            // Construire le reverse lookup
            foreach ($variants as $variantData) {
                $variantName = $variantData['variant'];
                $this->reverseMapping[strtolower($variantName)] = $normalizedName;
            }
        }
    }


    /**
     * Normalise un nom de colonne CSV vers son équivalent BDD
     *
    /**
     * Normalise un nom de colonne CSV vers son équivalent BDD
     *
     * @param string $csvColumnName Nom de colonne tel qu'il apparaît dans le CSV
     * @return string|null Nom normalisé pour la BDD ou null si non reconnu
     */
    public function normalizeColumnName(string $csvColumnName): ?string
    {
        $key = strtolower(trim($csvColumnName));
        return $this->reverseMapping[$key] ?? null;
    }

    /**
     * Analyse un header CSV et retourne le mapping complet
     * Gère automatiquement les doublons en sélectionnant la meilleure source
     *
     * @param array $csvHeaders Tableau des noms de colonnes du CSV
     * @return array [
     *   'mapped' => ['db_name' => ['csv_column' => 'Original Name', 'priority' => 0, 'index' => 5]],
     *   'unmapped' => ['Unknown Column 1', 'Unknown Column 2'],
     *   'duplicates' => ['db_name' => [['csv_column' => 'Name1', 'priority' => 0], ['csv_column' => 'Name2', 'priority' => 1]]]
     * ]
     */
    public function mapCsvHeaders(array $csvHeaders): array
    {
        $mapped = [];
        $unmapped = [];
        $duplicates = [];

        foreach ($csvHeaders as $index => $originalName) {
            // Trim whitespace from column names
            $originalName = trim($originalName);

            $normalizedName = $this->normalizeColumnName($originalName);

            if ($normalizedName === null) {
                $unmapped[] = $originalName;
                continue;
            }

            // Construire l'entrée avec priorité et index
            $priority = $this->getVariantPriority($normalizedName, $originalName);
            $entry = [
                'csv_column' => $originalName,
                'priority' => $priority,
                'index' => $index,
            ];

            // Détection des doublons (plusieurs sources pour la même donnée)
            if (isset($mapped[$normalizedName])) {
                // Keep track of duplicates
                if (!isset($duplicates[$normalizedName])) {
                    $duplicates[$normalizedName] = [$mapped[$normalizedName]];
                }
                $duplicates[$normalizedName][] = $entry;

                // Keep the one with best priority
                if ($priority < $mapped[$normalizedName]['priority']) {
                    $mapped[$normalizedName] = $entry;
                }
            } else {
                $mapped[$normalizedName] = $entry;
            }
        }

        return [
            'mapped' => $mapped,              // normalized => ['csv_column' => ..., 'priority' => ..., 'index' => ...]
            'unmapped' => $unmapped,          // colonnes non reconnues
            'duplicates' => $duplicates,      // doublons par nom normalisé
        ];
    }

    /**
     * Trouve la priorité d'une variante spécifique
     */
    private function getVariantPriority(string $normalizedName, string $variantName): int
    {
        if (!isset($this->columnMapping[$normalizedName])) {
            return 999; // Priorité par défaut si non trouvée
        }

        $key = strtolower($variantName);
        foreach ($this->columnMapping[$normalizedName] as $variantData) {
            if (strtolower($variantData['variant']) === $key) {
                return $variantData['priority'];
            }
        }

        return 999;
    }

    /**
     * Extrait uniquement les colonnes disponibles (noms normalisés)
     *
     * @param array $csvHeaders Tableau des noms de colonnes du CSV
     * @return array Liste des noms de colonnes normalisés disponibles
     */
    public function getAvailableColumns(array $csvHeaders): array
    {
        $mapping = $this->mapCsvHeaders($csvHeaders);
        return array_keys($mapping['mapped']);
    }

    /**
     * Retourne la liste de toutes les colonnes reconnues par le système
     *
     * @return array Liste des noms de colonnes BDD
     */
    public function getAllKnownColumns(): array
    {
        return array_keys($this->columnMapping);
    }

    /**
     * Retourne toutes les variantes connues pour une colonne BDD
     *
     * @param string $dbColumnName Nom de colonne en BDD
     * @return array|null Liste des variantes ou null si colonne inconnue
     */
    public function getVariants(string $dbColumnName): ?array
    {
        if (!isset($this->columnMapping[$dbColumnName])) {
            return null;
        }

        return array_map(
            fn($v) => $v['variant'],
            $this->columnMapping[$dbColumnName]
        );
    }

    /**
     * Retourne des statistiques sur le mapping d'un CSV
     *
     * @param array $csvHeaders Headers du CSV
     * @return array Statistiques de mapping
     */
    public function getMappingStats(array $csvHeaders): array
    {
        $mapping = $this->mapCsvHeaders($csvHeaders);

        return [
            'total_columns' => count($csvHeaders),
            'mapped_columns' => count($mapping['mapped']),
            'unmapped_columns' => count($mapping['unmapped']),
            'duplicate_sources' => count($mapping['duplicates']),
            'mapping_rate' => count($csvHeaders) > 0
                ? round((count($mapping['mapped']) / count($csvHeaders)) * 100, 2)
                : 0
        ];
    }
}
