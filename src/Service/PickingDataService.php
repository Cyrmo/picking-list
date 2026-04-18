<?php
/**
 * DFS Picking List — Service de données
 *
 * Construit et exécute la requête SQL principale pour la picking list.
 * Intègre les deux sources de dates métier :
 *   - DFS Click & Collect (priorité 1) : dfs_clickcollect_creneau.day
 *   - DFS Delivery Date (priorité 2)   : dfs_delivery_order.delivery_date
 *   - Fallback (priorité 3)            : orders.date_add
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
     * Retourne les données agrégées de picking selon les filtres fournis.
     *
     * @param array $filters Filtres validés (mode, type, value, date_from, date_to, is_range)
     *
     * @return array{rows: array, show_date_col: bool}
     */
    public function getPickingData(array $filters): array
    {
        if (empty($filters['mode']) || empty($filters['date_from'])) {
            return ['rows' => [], 'show_date_col' => false];
        }

        $idLang  = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShops = $this->getShopIds();
        $isRange = (bool) $filters['is_range'];

        // Élargissement GROUP_CONCAT — protection anti-troncature (1 Mo par valeur)
        $this->db->execute('SET SESSION group_concat_max_len = 1048576');

        // Construction des clauses WHERE
        $modeWhere = $this->buildModeWhere($filters);
        $dateWhere = $this->buildDateWhere($filters['date_from'], $filters['date_to']);

        // GROUP BY : ajout de picking_date uniquement en mode plage multi-jours
        $groupBy = 'od.product_id, od.product_attribute_id, carrier_label';
        if ($isRange) {
            $groupBy .= ', picking_date';
        }

        $orderBy = 'od.product_name ASC' . ($isRange ? ', picking_date ASC' : '');

        $sql = '
            SELECT
                od.product_id,
                od.product_attribute_id,
                od.product_name,
                SUM(od.product_quantity) AS total_qty,

                -- Label enrichi : "Retrait en boutique — Lieu" ou nom du transporteur
                CASE
                    WHEN cr.id_store IS NOT NULL
                        THEN CONCAT(
                            COALESCE(c.`name`, \'Retrait en boutique\'),
                            \' — \',
                            COALESCE(sl.`name`, \'\')
                        )
                    ELSE COALESCE(c.`name`, \'[Transporteur supprimé]\')
                END AS carrier_label,

                -- Commandes associées (séparées par "; ")
                GROUP_CONCAT(
                    DISTINCT o.reference
                    ORDER BY o.reference ASC
                    SEPARATOR \'; \'
                ) AS orders_list,

                -- Date picking unifiée (uniquement dans SELECT, jamais dans WHERE)
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

            -- Anti-duplication : 1 créneau C&C maximum par commande (le plus récent)
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

            -- Nom du lieu de retrait (depuis table PS store_lang)
            LEFT JOIN `' . _DB_PREFIX_ . 'store_lang` sl
                ON sl.id_store = cr.id_store
                AND sl.id_lang = ' . $idLang . '

            -- Date de livraison (DFS Delivery Date)
            LEFT JOIN `' . _DB_PREFIX_ . 'dfs_delivery_order` dd
                ON dd.id_order = o.id_order

            WHERE
                -- États exclus (via constantes PS — aucun entier codé en dur)
                o.current_state NOT IN (' . $this->buildExcludedStatesClause() . ')

                -- Filtre boutique (multiboutique respecté)
                AND o.id_shop IN (' . implode(',', array_map('intval', $idShops)) . ')

                -- Produits annulés exclus
                AND od.product_quantity > 0

                -- Filtre mode de livraison (carrier:X ou clickcollect:Y)
                AND (' . $modeWhere . ')

                -- Filtre date (branching OR sans COALESCE dans le WHERE = index-friendly)
                AND (' . $dateWhere . ')

            GROUP BY ' . $groupBy . '
            ORDER BY ' . $orderBy . '
        ';

        $rows = $this->db->executeS($sql);

        if (!is_array($rows)) {
            $rows = [];
        }

        // Validation PHP anti-troncature GROUP_CONCAT
        foreach ($rows as &$row) {
            $trimmed = rtrim((string) ($row['orders_list'] ?? ''));
            $row['orders_list_truncated'] = str_ends_with($trimmed, ';');
        }
        unset($row);

        return [
            'rows'         => $rows,
            'show_date_col' => $isRange,
        ];
    }

    // -------------------------------------------------------------------------
    // Construction des clauses WHERE
    // -------------------------------------------------------------------------

    /**
     * Construit le prédicat SQL pour le filtre "mode de livraison".
     *
     * Pour carrier:X  → exclut explicitement les commandes C&C (cr.id_order IS NULL)
     * Pour clickcollect:Y → filtre sur le transporteur C&C ET le lieu précis
     */
    private function buildModeWhere(array $filters): string
    {
        if ($filters['type'] === 'carrier') {
            // Transporteur standard — exclusion C&C structurelle
            return 'c.id_reference = ' . (int) $filters['value'] . '
                    AND cr.id_order IS NULL';
        }

        if ($filters['type'] === 'clickcollect') {
            $ccRef = $this->getClickCollectCarrierReference();
            if (!$ccRef) {
                return '1=0'; // Sécurité : aucun transporteur C&C trouvé
            }
            return 'c.id_reference = ' . (int) $ccRef . '
                    AND cr.id_store = ' . (int) $filters['value'];
        }

        return '1=0'; // Filtre vide — ne doit pas arriver en pratique
    }

    /**
     * Construit le prédicat SQL pour le filtre date.
     *
     * Utilise 3 branches OR explicites (sans COALESCE dans le WHERE)
     * pour préserver l'usage des indexes sur chaque colonne source.
     *
     * Priorité :
     *   1. dfs_clickcollect_creneau.day (date retrait C&C)
     *   2. dfs_delivery_order.delivery_date (date livraison)
     *   3. orders.date_add (fallback — sans DATE() pour préserver l'index)
     */
    private function buildDateWhere(string $from, string $to): string
    {
        $from = pSQL($from);
        $to   = pSQL($to ?: $from);

        return "
            /* Priorité 1 : créneau Click & Collect avec date valide */
            (
                cr.id_order IS NOT NULL
                AND cr.`day` <> '0000-00-00'
                AND cr.`day` BETWEEN '{$from}' AND '{$to}'
            )
            /* Priorité 2 : pas de C&C (ou date invalide) + date livraison valide */
            OR (
                (cr.id_order IS NULL OR cr.`day` = '0000-00-00')
                AND dd.id_order IS NOT NULL
                AND dd.delivery_date <> '0000-00-00'
                AND dd.delivery_date BETWEEN '{$from}' AND '{$to}'
            )
            /* Priorité 3 : fallback sur date de commande (sans DATE() = index utilisé) */
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
     * Construit la liste CSV des états exclus depuis les constantes PS
     * (jamais d'entiers codés en dur).
     */
    private function buildExcludedStatesClause(): string
    {
        $states = array_filter([
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_REFUND'),
            (int) Configuration::get('PS_OS_ERROR'),
        ]);

        $custom = (string) Configuration::get('DFSPICKING_EXCLUDED_STATES');
        if ($custom !== '') {
            $customIds = array_filter(array_map('intval', explode(',', $custom)));
            $states    = array_unique(array_merge($states, $customIds));
        }

        $states = array_values(array_filter($states));

        // Fallback : si aucune constante PS définie, exclure 0 (aucun état)
        return $states ? implode(',', $states) : '0';
    }

    /**
     * Détermine dynamiquement l'id_reference du transporteur DFS Click & Collect.
     *
     * Méthode 1 (principale) : détection par les commandes C&C existantes (toujours fiable)
     * Méthode 2 (fallback)   : via table module_carrier (id_reference, pas id_carrier)
     */
    public function getClickCollectCarrierReference(): int
    {
        // Méthode 1 : détection par les données historiques (principale car toujours remplie)
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

        // Méthode 2 : via module_carrier PS (id_reference = colonne correcte, PAS id_carrier)
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
