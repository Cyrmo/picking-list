<?php
/**
 * DFS Picking List — Service de données
 *
 * v1.1.0 — Filtre état de commande + clauses mode/date conditionnelles
 *
 * Priorité des dates (inchangée) :
 *   1. dfs_clickcollect_creneau.day
 *   2. dfs_delivery_order.delivery_date
 *   3. orders.date_add (fallback)
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PickingDataService
{
    /** @var Context */
    private $context;

    /** @var Db */
    private $db;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->db      = Db::getInstance();
    }

    // -------------------------------------------------------------------------
    // Point d'entrée public
    // -------------------------------------------------------------------------

    /**
     * Retourne les données agrégées selon les filtres.
     *
     * v1.1 : mode et date deviennent optionnels (mode OU, pas ET).
     * L'état de commande est toujours appliqué (défaut = commandes en cours).
     *
     * @param array $filters Filtres validés par le contrôleur
     *
     * @return array{rows: array, show_date_col: bool}
     */
    public function getPickingData(array $filters): array
    {
        // Sécurité : ne jamais exécuter sans au moins un critère
        if (empty($filters['has_filter'])) {
            return ['rows' => [], 'show_date_col' => false];
        }

        $idLang  = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShops = $this->getShopIds();
        $isRange = (bool) ($filters['is_range'] ?? false);

        // Protection GROUP_CONCAT (anti-troncature)
        $this->db->execute('SET SESSION group_concat_max_len = 1048576');

        // ---------------------------------------------------------------
        // Construction des clauses WHERE conditionnelles
        // ---------------------------------------------------------------

        // 1. État de commande (toujours présent)
        $stateWhere = $this->buildStateWhere($filters);

        // 2. Boutiques (toujours présent)
        $shopWhere = 'o.id_shop IN (' . implode(',', array_map('intval', $idShops)) . ')';

        // 3. Produits non annulés (toujours présent)
        $qtyWhere = 'od.product_quantity > 0';

        // 4. Mode de livraison (conditionnel)
        $modeWhere = '';
        if (!empty($filters['has_mode'])) {
            $modeWhere = $this->buildModeWhere($filters);
        }

        // 5. Date (conditionnelle)
        $dateWhere = '';
        if (!empty($filters['has_date'])) {
            $dateWhere = $this->buildDateWhere($filters['date_from'], $filters['date_to']);
        }

        // ---------------------------------------------------------------
        // Assemblage du WHERE final
        // ---------------------------------------------------------------
        $whereParts = [
            $stateWhere,
            $shopWhere,
            $qtyWhere,
        ];

        if ($modeWhere) {
            $whereParts[] = '(' . $modeWhere . ')';
        }

        if ($dateWhere) {
            $whereParts[] = '(' . $dateWhere . ')';
        }

        $where = implode("\n                AND ", $whereParts);

        // ---------------------------------------------------------------
        // GROUP BY / ORDER BY
        // ---------------------------------------------------------------
        $groupBy = 'od.product_id, od.product_attribute_id, carrier_label';
        if ($isRange) {
            $groupBy .= ', picking_date';
        }

        $orderBy = 'od.product_name ASC' . ($isRange ? ', picking_date ASC' : '');

        // ---------------------------------------------------------------
        // Requête principale
        // ---------------------------------------------------------------
        $sql = '
            SELECT
                od.product_id,
                od.product_attribute_id,
                od.product_name,
                SUM(od.product_quantity) AS total_qty,

                -- Label enrichi
                CASE
                    WHEN cr.id_store IS NOT NULL
                        THEN CONCAT(
                            COALESCE(c.`name`, \'Retrait en boutique\'),
                            \' — \',
                            COALESCE(sl.`name`, \'\')
                        )
                    ELSE COALESCE(c.`name`, \'[Transporteur supprimé]\')
                END AS carrier_label,

                -- Commandes associées (ID et Référence pour pouvoir générer les liens)
                GROUP_CONCAT(
                    DISTINCT CONCAT(o.id_order, \':\', o.reference)
                    ORDER BY o.reference ASC
                    SEPARATOR \'; \'
                ) AS orders_list,

                -- Date picking unifiée (COALESCE dans SELECT uniquement → index préservé)
                COALESCE(
                    NULLIF(cr.`day`, \'0000-00-00\'),
                    NULLIF(dd.delivery_date, \'0000-00-00\'),
                    DATE(o.date_add)
                ) AS picking_date

            FROM `' . _DB_PREFIX_ . 'order_detail` od

            INNER JOIN `' . _DB_PREFIX_ . 'orders` o
                ON o.id_order = od.id_order

            LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c
                ON c.id_carrier = o.id_carrier

            -- Anti-duplication : 1 créneau C&C par commande (le plus récent)
            LEFT JOIN (
                SELECT cr_inner.id_order, cr_inner.id_store, cr_inner.`day`, cr_inner.`hour`
                FROM `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau` cr_inner
                INNER JOIN (
                    SELECT id_order, MAX(id_creneau) AS latest_id
                    FROM `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau`
                    GROUP BY id_order
                ) latest
                    ON latest.id_order = cr_inner.id_order
                    AND latest.latest_id = cr_inner.id_creneau
            ) cr ON cr.id_order = o.id_order

            -- Nom du lieu de retrait
            LEFT JOIN `' . _DB_PREFIX_ . 'store_lang` sl
                ON sl.id_store = cr.id_store
                AND sl.id_lang = ' . $idLang . '

            -- DFS Delivery Date
            LEFT JOIN `' . _DB_PREFIX_ . 'dfs_delivery_order` dd
                ON dd.id_order = o.id_order

            WHERE
                ' . $where . '

            GROUP BY ' . $groupBy . '
            ORDER BY ' . $orderBy . '
        ';

        $rows = $this->db->executeS($sql);

        if (!is_array($rows)) {
            $rows = [];
        }

        // Traitement des données récupérées (création des URLs de commandes)
        foreach ($rows as &$row) {
            $rawList = (string) ($row['orders_list'] ?? '');
            $trimmed = rtrim($rawList, '; ');
            $row['orders_list_truncated'] = str_ends_with($rawList, ';');

            $ordersLinks = [];
            $ordersText  = [];

            if ($trimmed !== '') {
                $pairs = explode('; ', $trimmed);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) === 2) {
                        $idOrder = (int) $parts[0];
                        $ref     = $parts[1];
                        $ordersText[] = $ref;
                        $ordersLinks[] = [
                            'ref' => $ref,
                            'url' => $this->context->link->getAdminLink('AdminOrders', true, [], [
                                'id_order'  => $idOrder,
                                'vieworder' => 1
                            ])
                        ];
                    } else {
                        // Cas exceptionnel (ex: troncature au milieu du GROUP_CONCAT)
                        $cleanRef = preg_replace('/^\d+:/', '', $pair);
                        $ordersText[] = $cleanRef;
                    }
                }
            }

            // On réécrit orders_list en texte brut "REF1; REF2" (pour exports CSV/XLSX/PDF)
            $row['orders_list'] = implode('; ', $ordersText);
            // On fournit les données avec liens pour le template Smarty (BO)
            $row['orders_links_data'] = $ordersLinks;
        }
        unset($row);

        return [
            'rows'          => $rows,
            'show_date_col' => $isRange,
        ];
    }

    // -------------------------------------------------------------------------
    // Construction des clauses WHERE
    // -------------------------------------------------------------------------

    /**
     * Filtre État de commande (v1.1).
     *
     * Si l'utilisateur a sélectionné des états → IN (...)
     * Sinon → comportement par défaut : exclure livré, remboursé, annulé, erreur
     *          (= commandes "en cours")
     */
    private function buildStateWhere(array $filters): string
    {
        $states = $filters['states'] ?? [];

        if (!empty($states)) {
            // États explicitement sélectionnés (remplace entièrement le comportement par défaut)
            return 'o.current_state IN (' . implode(',', array_map('intval', $states)) . ')';
        }

        // Comportement par défaut : commandes en cours uniquement
        $excluded = array_filter([
            (int) Configuration::get('PS_OS_DELIVERED'),
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_REFUND'),
            (int) Configuration::get('PS_OS_ERROR'),
        ]);

        // États personnalisés éventuellement configurés dans le module
        $custom = (string) Configuration::get('DFSPICKING_EXCLUDED_STATES');
        if ($custom !== '') {
            $customIds = array_filter(array_map('intval', explode(',', $custom)));
            $excluded  = array_unique(array_merge($excluded, $customIds));
        }

        $excluded = array_values(array_filter($excluded));

        return $excluded
            ? 'o.current_state NOT IN (' . implode(',', $excluded) . ')'
            : '1=1';
    }

    /**
     * Filtre mode de livraison.
     *
     * carrier:X    → transporteur standard, C&C structurellement exclus
     * clickcollect:Y → transporteur C&C + lieu de retrait précis
     *
     * Appelé uniquement si has_mode = true.
     */
    private function buildModeWhere(array $filters): string
    {
        if ($filters['type'] === 'carrier') {
            return 'c.id_reference = ' . (int) $filters['value'] . '
                    AND cr.id_order IS NULL';
        }

        if ($filters['type'] === 'clickcollect') {
            $ccRef = $this->getClickCollectCarrierReference();
            if (!$ccRef) {
                return '1=0';
            }
            return 'c.id_reference = ' . (int) $ccRef . '
                    AND cr.id_store = ' . (int) $filters['value'];
        }

        return '1=0';
    }

    /**
     * Filtre date — 3 branches OR index-friendly (sans COALESCE dans le WHERE).
     *
     * Priorité :
     *   1. dfs_clickcollect_creneau.day
     *   2. dfs_delivery_order.delivery_date
     *   3. orders.date_add
     *
     * Appelé uniquement si has_date = true.
     */
    private function buildDateWhere(string $from, string $to): string
    {
        $from = pSQL($from);
        $to   = pSQL($to ?: $from);

        return "
            /* Priorité 1 : créneau Click & Collect */
            (
                cr.id_order IS NOT NULL
                AND cr.`day` <> '0000-00-00'
                AND cr.`day` BETWEEN '{$from}' AND '{$to}'
            )
            /* Priorité 2 : date de livraison DFS */
            OR (
                (cr.id_order IS NULL OR cr.`day` = '0000-00-00')
                AND dd.id_order IS NOT NULL
                AND dd.delivery_date <> '0000-00-00'
                AND dd.delivery_date BETWEEN '{$from}' AND '{$to}'
            )
            /* Priorité 3 : date de commande (sans DATE() → index utilisé) */
            OR (
                (cr.id_order IS NULL OR cr.`day` = '0000-00-00')
                AND (dd.id_order IS NULL OR dd.delivery_date = '0000-00-00')
                AND o.date_add BETWEEN '{$from} 00:00:00' AND '{$to} 23:59:59'
            )
        ";
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Détermine l'id_reference du transporteur DFS Click & Collect.
     *
     * Méthode 1 (principale) : commandes C&C existantes (toujours fiable)
     * Méthode 2 (fallback)   : module_carrier PS (id_reference)
     */
    public function getClickCollectCarrierReference(): int
    {
        $ref = (int) $this->db->getValue(
            'SELECT c.id_reference
             FROM `' . _DB_PREFIX_ . 'carrier` c
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_carrier = c.id_carrier
             INNER JOIN `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau` cr ON cr.id_order = o.id_order
             WHERE c.`deleted` = 0
             GROUP BY c.id_reference
             ORDER BY COUNT(*) DESC'
        );

        if ($ref > 0) {
            return $ref;
        }

        $ref = (int) $this->db->getValue(
            'SELECT cm.id_reference
             FROM `' . _DB_PREFIX_ . 'module_carrier` cm
             INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.id_module = cm.id_module
             WHERE m.`name` = \'dfs_clickcollect\''
        );

        return $ref;
    }

    /**
     * Retourne les IDs de boutiques selon le contexte multiboutique actif.
     */
    private function getShopIds(): array
    {
        $shopContext = Shop::getContext();

        if ($shopContext === Shop::CONTEXT_ALL) {
            return array_column(Shop::getShops(true), 'id_shop');
        }

        if ($shopContext === Shop::CONTEXT_GROUP) {
            return array_column(
                Shop::getShops(true, $this->context->shop->id_shop_group),
                'id_shop'
            );
        }

        return [(int) $this->context->shop->id];
    }
}
