<?php

namespace App\Service;

use App\Entity\Trip;
use ZipArchive;

class TripProcessingService
{
    /**
     * Décompresse un fichier ZIP Torque Pro et retourne le chemin du CSV extrait
     * 
     * @param Trip $trip L'entité Trip contenant le chemin du fichier ZIP
     * @return string|null Le chemin du fichier CSV extrait, ou null en cas d'erreur
     * @throws \Exception Si le ZIP ne peut pas être ouvert ou ne contient pas de CSV
     */
    public function extractCsvFromZip(Trip $trip): ?string
    {
        $zipPath = $trip->getFilePath();
        
        if (!file_exists($zipPath)) {
            throw new \Exception("ZIP file not found: {$zipPath}");
        }

        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Cannot open ZIP file: {$zipPath}");
        }

        // Créer un répertoire temporaire pour l'extraction
        $extractDir = dirname($zipPath) . '/extracted_' . $trip->getId();
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        // Extraire tout le contenu du ZIP
        $zip->extractTo($extractDir);
        $zip->close();

        // Chercher le fichier CSV (généralement dans data/trackLog-*.csv)
        $csvPath = $this->findCsvFile($extractDir);
        
        if (!$csvPath) {
            // Nettoyer le répertoire d'extraction
            $this->removeDirectory($extractDir);
            throw new \Exception("No CSV file found in ZIP archive");
        }

        return $csvPath;
    }

    /**
     * Recherche récursive du fichier CSV dans le répertoire extrait
     * 
     * @param string $directory Le répertoire à explorer
     * @return string|null Le chemin complet du fichier CSV trouvé
     */
    private function findCsvFile(string $directory): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'csv') {
                // Torque Pro génère des fichiers trackLog-*.csv
                if (str_contains($file->getFilename(), 'trackLog')) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    /**
     * Analyse les métadonnées de base du fichier CSV
     * 
     * @param string $csvPath Chemin vers le fichier CSV
     * @return array Métadonnées du trajet (nombre de lignes, colonnes, etc.)
     */
    public function analyzeCsvMetadata(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new \Exception("CSV file not found: {$csvPath}");
        }

        $file = fopen($csvPath, 'r');
        
        // Lire l'en-tête
        $header = fgetcsv($file);
        $columnCount = count($header);
        
        // Compter les lignes de données
        $dataRowCount = 0;
        while (fgetcsv($file) !== false) {
            $dataRowCount++;
        }
        
        fclose($file);

        // Lire la première et dernière ligne pour les timestamps
        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header
        $firstRow = fgetcsv($file);
        
        // Aller à la dernière ligne
        $lastRow = null;
        while (($row = fgetcsv($file)) !== false) {
            $lastRow = $row;
        }
        fclose($file);

        // Extraire les timestamps (colonne 0 = GPS Time, colonne 1 = Device Time)
        $startTime = null;
        $endTime = null;
        $duration = null;

        if ($firstRow && $lastRow) {
            try {
                // Parser le format: "Thu Oct 23 12:00:11 GMT+02:00 2025"
                $startTime = $this->parseGpsTime($firstRow[0]);
                $endTime = $this->parseGpsTime($lastRow[0]);
                
                if ($startTime && $endTime) {
                    $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
                }
            } catch (\Exception $e) {
                // Ignore parsing errors
            }
        }

        return [
            'column_count' => $columnCount,
            'data_row_count' => $dataRowCount,
            'columns' => $header,
            'start_time' => $startTime?->format('Y-m-d H:i:s'),
            'end_time' => $endTime?->format('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
            'file_size' => filesize($csvPath),
        ];
    }

    /**
     * Parse le format de timestamp GPS de Torque Pro
     * Format: "Thu Oct 23 12:00:11 GMT+02:00 2025"
     * 
     * @param string $gpsTime Le timestamp GPS
     * @return \DateTimeImmutable|null
     */
    private function parseGpsTime(string $gpsTime): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($gpsTime);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Nettoie le répertoire d'extraction après traitement
     * 
     * @param string $extractDir Le répertoire à supprimer
     */
    public function cleanupExtractedFiles(string $extractDir): void
    {
        if (is_dir($extractDir)) {
            $this->removeDirectory($extractDir);
        }
    }

    /**
     * Supprime récursivement un répertoire et son contenu
     * 
     * @param string $dir Le répertoire à supprimer
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Traitement complet d'un trip: extraction + analyse
     * 
     * @param Trip $trip L'entité Trip à traiter
     * @return array Les métadonnées extraites
     */
    public function processTrip(Trip $trip): array
    {
        // 1. Extraire le CSV du ZIP
        $csvPath = $this->extractCsvFromZip($trip);

        // 2. Analyser les métadonnées
        $metadata = $this->analyzeCsvMetadata($csvPath);

        // 3. Mettre à jour l'entité Trip avec les métadonnées
        if ($metadata['start_time']) {
            $trip->setSessionDate(new \DateTimeImmutable($metadata['start_time']));
        }
        if ($metadata['duration_seconds']) {
            $trip->setDuration($metadata['duration_seconds']);
        }
        if ($metadata['data_row_count']) {
            $trip->setDataPointsCount($metadata['data_row_count']);
        }

        // 4. Garder le chemin du CSV pour un traitement ultérieur
        $metadata['csv_path'] = $csvPath;
        $metadata['extract_dir'] = dirname($csvPath);

        return $metadata;
    }
}
