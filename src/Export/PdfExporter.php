<?php
/**
 * DFS Picking List — Export PDF
 *
 * Génère un PDF via TCPDF (natif PrestaShop 9).
 * Format paysage pour accommoder toutes les colonnes.
 * Aucun appel cURL — génération directe en mémoire.
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdfExporter
{
    // Largeurs de colonnes (mm) selon présence de la colonne DATE
    private const COL_WIDTHS_NO_DATE   = [105, 20, 65, 90];
    private const COL_WIDTHS_WITH_DATE = [90,  20, 55, 32, 83];

    /**
     * Génère et envoie le PDF au navigateur (téléchargement direct).
     *
     * @param array[] $rows    Données issues de PickingDataService
     * @param array   $filters Filtres actifs
     */
    public function export(array $rows, array $filters): void
    {
        $showDate  = !empty($filters['is_range']);
        $colWidths = $showDate ? self::COL_WIDTHS_WITH_DATE : self::COL_WIDTHS_NO_DATE;

        $headers = ['NOM PRODUIT', 'QTÉ', 'MODE DE LIVRAISON'];
        if ($showDate) {
            $headers[] = 'DATE PICKING';
        }
        $headers[] = 'COMMANDES';

        // Initialisation TCPDF
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('DFS Picking List');
        $pdf->SetAuthor('Cyrille Mohr - Digital Food System');
        $pdf->SetTitle('Picking List — ' . date('d/m/Y'));
        $pdf->SetSubject('Liste de préparation commandes');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 10);

        $pdf->AddPage();

        // --- Titre ---
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(0, 10, 'Picking List — ' . date('d/m/Y'), 0, 1, 'C');

        // Sous-titre : plage de dates ou date unique
        if (!empty($filters['date_from'])) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(100, 100, 100);

            if ($showDate) {
                $subtitle = 'Du ' . $this->formatDate($filters['date_from'])
                    . ' au ' . $this->formatDate($filters['date_to']);
            } else {
                $subtitle = 'Date : ' . $this->formatDate($filters['date_from']);
            }

            $pdf->Cell(0, 5, $subtitle, 0, 1, 'C');
        }

        $pdf->Ln(4);

        // --- En-tête du tableau ---
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(45, 45, 45);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);

        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // --- Lignes de données ---
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $fillAlt = false;

        foreach ($rows as $row) {
            $pdf->SetFillColor(
                $fillAlt ? 245 : 255,
                $fillAlt ? 245 : 255,
                $fillAlt ? 245 : 255
            );

            $cells = [
                $this->truncate($row['product_name']  ?? '', 55),
                $row['total_qty'] ?? 0,
                $this->truncate($row['carrier_label'] ?? '', 32),
            ];

            if ($showDate) {
                $cells[] = $this->formatDate($row['picking_date'] ?? '');
            }

            $cells[] = $this->truncate($row['orders_list'] ?? '', 55);

            // Calcul hauteur de ligne (MultiCell pour le nom du produit)
            $lineH = 6;

            foreach ($cells as $i => $cell) {
                $align = ($i === 1) ? 'C' : 'L'; // quantité centrée
                $pdf->Cell($colWidths[$i], $lineH, (string) $cell, 1, 0, $align, $fillAlt);
            }

            // Avertissement troncature GROUP_CONCAT
            if (!empty($row['orders_list_truncated'])) {
                $pdf->SetFont('helvetica', 'I', 6);
                $pdf->SetTextColor(180, 0, 0);
                $pdf->Cell(0, 0, '⚠ liste tronquée', 0, 0, 'R');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(30, 30, 30);
            }

            $pdf->Ln();
            $fillAlt = !$fillAlt;
        }

        // --- Pied de page : comptage ---
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(130, 130, 130);
        $productCount = count($rows);
        $pdf->Cell(0, 5, $productCount . ' ligne(s) — Généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');

        // --- Envoi ---
        $filename = 'picking-list-' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Tronque une chaîne à la longueur max avec ellipse.
     */
    private function truncate(string $str, int $max): string
    {
        if (mb_strlen($str) <= $max) {
            return $str;
        }
        return mb_substr($str, 0, $max - 3) . '...';
    }

    /**
     * Formate une date ISO (YYYY-MM-DD) en format français (DD/MM/YYYY).
     * Retourne la chaîne d'origine si le format ne correspond pas.
     */
    private function formatDate(string $date): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $date;
    }
}
