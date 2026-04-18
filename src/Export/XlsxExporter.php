<?php
/**
 * DFS Picking List — Export XLSX
 *
 * Génère un fichier Excel via PhpSpreadsheet (disponible dans PS9).
 * En cas d'absence de la librairie, fallback automatique vers CSV.
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class XlsxExporter
{
    /**
     * Envoie le fichier XLSX au navigateur (téléchargement direct).
     *
     * @param array[] $rows    Données issues de PickingDataService
     * @param array   $filters Filtres actifs
     */
    public function export(array $rows, array $filters): void
    {
        // Fallback CSV si PhpSpreadsheet absent
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            (new CsvExporter())->export($rows, $filters);
            return;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Picking List');

        // --- En-têtes ---
        $headers = ['NOM PRODUIT', 'QUANTITÉ', 'MODE DE LIVRAISON'];
        if (!empty($filters['is_range'])) {
            $headers[] = 'DATE PICKING';
        }
        $headers[] = 'COMMANDES';

        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $sheet->getStyleByColumnAndRow($colIndex, 1)
                  ->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow($colIndex, 1)
                  ->getFill()
                  ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('2D2D2D');
            $sheet->getStyleByColumnAndRow($colIndex, 1)
                  ->getFont()->getColor()->setRGB('FFFFFF');
            $colIndex++;
        }

        // --- Données ---
        $rowIndex = 2;
        $fillAlt  = false;

        foreach ($rows as $row) {
            $colIndex = 1;

            $cells = [
                $row['product_name']  ?? '',
                (int) ($row['total_qty'] ?? 0),
                $row['carrier_label'] ?? '',
            ];
            if (!empty($filters['is_range'])) {
                $cells[] = $row['picking_date'] ?? '';
            }
            $cells[] = $row['orders_list'] ?? '';

            foreach ($cells as $cellValue) {
                $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue($cellValue);

                // Fond alterné pour lisibilité
                if ($fillAlt) {
                    $sheet->getStyleByColumnAndRow($colIndex, $rowIndex)
                          ->getFill()
                          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                          ->getStartColor()->setRGB('F5F5F5');
                }
                $colIndex++;
            }

            $fillAlt = !$fillAlt;
            $rowIndex++;
        }

        // --- Largeur automatique des colonnes ---
        $maxCol = count($headers);
        for ($c = 1; $c <= $maxCol; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        // --- Envoi au navigateur ---
        $filename = 'picking-list-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}
