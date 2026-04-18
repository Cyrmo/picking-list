<?php
/**
 * DFS Picking List — Export CSV
 *
 * Génère un fichier CSV UTF-8 avec BOM (compatible Excel français).
 * Séparateur : ";" — Colonnes : NOM PRODUIT, QUANTITÉ, MODE DE LIVRAISON,
 * [DATE PICKING si plage multi-jours], COMMANDES.
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CsvExporter
{
    /**
     * Envoie le CSV au navigateur (téléchargement direct).
     *
     * @param array[] $rows    Données issues de PickingDataService
     * @param array   $filters Filtres actifs (pour la colonne date conditionnelle)
     */
    public function export(array $rows, array $filters): void
    {
        $filename = 'picking-list-' . date('Y-m-d') . '.csv';

        // Headers HTTP
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM UTF-8 — indispensable pour que Excel FR ouvre correctement l'encodage
        fputs($output, "\xEF\xBB\xBF");

        // En-têtes de colonnes
        $headers = ['NOM PRODUIT', 'QUANTITÉ', 'MODE DE LIVRAISON'];
        if (!empty($filters['is_range'])) {
            $headers[] = 'DATE PICKING';
        }
        $headers[] = 'COMMANDES';

        fputcsv($output, $headers, ';');

        // Lignes de données
        foreach ($rows as $row) {
            $line = [
                $row['product_name']  ?? '',
                $row['total_qty']     ?? 0,
                $row['carrier_label'] ?? '',
            ];

            if (!empty($filters['is_range'])) {
                $line[] = $row['picking_date'] ?? '';
            }

            $line[] = $row['orders_list'] ?? '';

            fputcsv($output, $line, ';');
        }

        fclose($output);
    }
}
