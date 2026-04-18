<?php
/**
 * DFS Picking List — Construction des options de filtre enrichies
 *
 * Construit dynamiquement la liste des "modes de livraison" pour le filtre :
 * - Transporteurs standards → clé "carrier:{id_reference}"
 * - Lieux Click & Collect  → clé "clickcollect:{id_store}"
 *
 * Aucun transporteur, lieu ou ID n'est codé en dur.
 * Tout est résolu à l'exécution depuis la base de données.
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrierEnricher
{
    /** @var Db */
    private $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    // -------------------------------------------------------------------------
    // Point d'entrée public
    // -------------------------------------------------------------------------

    /**
     * Construit la liste des options pour le <select> "Mode de livraison".
     *
     * Structure de chaque option :
     *   ['key' => 'carrier:7',        'name' => 'Novéa']
     *   ['key' => 'clickcollect:4',   'name' => 'Retrait en boutique — Strasbourg']
     *
     * @param int   $idLang  Langue pour les labels
     * @param int[] $idShops Boutiques du contexte courant
     *
     * @return array[]
     */
    public function buildFilterOptions(int $idLang, array $idShops): array
    {
        $options   = [];
        $ccRef     = $this->getClickCollectCarrierReference();

        // 1. Transporteurs standards actifs (hors Click & Collect)
        $carriers = Carrier::getCarriers($idLang, true, false, false, null, Carrier::ALL_CARRIERS);
        $seen     = [];

        foreach ($carriers as $carrier) {
            $ref = (int) $carrier['id_reference'];

            // Exclure le transporteur Click & Collect de la liste standard
            if ($ccRef > 0 && $ref === $ccRef) {
                continue;
            }

            // Dédoublonnage par id_reference (PS peut retourner plusieurs lignes)
            if (isset($seen[$ref])) {
                continue;
            }
            $seen[$ref] = true;

            $options[] = [
                'key'  => 'carrier:' . $ref,
                'name' => $carrier['name'],
            ];
        }

        // 2. Lieux de retrait Click & Collect (dynamiques depuis DFS C&C)
        if ($ccRef > 0 && !empty($idShops)) {
            $stores = $this->getClickCollectStores($idLang, $idShops);

            foreach ($stores as $store) {
                $options[] = [
                    'key'  => 'clickcollect:' . (int) $store['id_store'],
                    'name' => 'Retrait en boutique — ' . $store['name'],
                ];
            }
        }

        return $options;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Récupère les lieux de retrait configurés dans DFS Click & Collect.
     *
     * Jointure : dfs_clickcollect_store → store → store_lang
     * Résultat : liste distincte des magasins actifs pour les boutiques données.
     *
     * @param int   $idLang
     * @param int[] $idShops
     *
     * @return array[]
     */
    private function getClickCollectStores(int $idLang, array $idShops): array
    {
        $idShopsClause = implode(',', array_map('intval', $idShops));

        $rows = $this->db->executeS(
            'SELECT DISTINCT s.id_store, sl.`name`
             FROM `' . _DB_PREFIX_ . 'dfs_clickcollect_store` cs
             INNER JOIN `' . _DB_PREFIX_ . 'store` s
                 ON s.id_store = cs.id_store
             INNER JOIN `' . _DB_PREFIX_ . 'store_lang` sl
                 ON sl.id_store = s.id_store
                 AND sl.id_lang = ' . $idLang . '
             WHERE cs.id_shop IN (' . $idShopsClause . ')
             ORDER BY sl.`name` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Détermine dynamiquement l'id_reference du transporteur DFS Click & Collect.
     *
     * Méthode 1 : via les commandes ayant un créneau C&C (fiable, base de données réelle)
     * Méthode 2 : via la table module_carrier (liaison PS officielle, si configurée)
     * Retourne 0 si le module C&C n'est pas installé ou n'a pas de transporteur.
     */
    public function getClickCollectCarrierReference(): int
    {
        // Méthode 1 (principale) : détection par les commandes C&C existantes
        // Trouve l'id_reference du transporteur le plus souvent associé à un créneau C&C
        $ref = (int) $this->db->getValue(
            'SELECT c.id_reference
             FROM `' . _DB_PREFIX_ . 'carrier` c
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_carrier = c.id_carrier
             INNER JOIN `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau` cr
                 ON cr.id_order = o.id_order
             WHERE c.`deleted` = 0
             GROUP BY c.id_reference
             ORDER BY COUNT(*) DESC'
        );

        if ($ref > 0) {
            return $ref;
        }

        // Méthode 2 (fallback) : via table module_carrier PS
        // Note: module_carrier.id_reference = id_reference du transporteur (pas id_carrier)
        $ref = (int) $this->db->getValue(
            'SELECT cm.id_reference
             FROM `' . _DB_PREFIX_ . 'module_carrier` cm
             INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.id_module = cm.id_module
             WHERE m.`name` = \'dfs_clickcollect\''
        );

        return $ref;
    }
}
