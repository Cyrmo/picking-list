<?php
/**
 * DFS Picking List
 *
 * Génère une liste de préparation des produits à partir d'un transporteur
 * et d'une date ou plage de dates.
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/src/Service/PickingDataService.php';
require_once __DIR__ . '/src/Service/CarrierEnricher.php';
require_once __DIR__ . '/src/Export/CsvExporter.php';
require_once __DIR__ . '/src/Export/XlsxExporter.php';
require_once __DIR__ . '/src/Export/PdfExporter.php';

class Dfs_pickinglist extends Module
{
    /**
     * Déclaration du Tab dans le menu BO (Commandes > Picking List)
     */
    public $tabs = [
        [
            'name'              => [
                'fr' => 'Picking List',
                'en' => 'Picking List',
                'de' => 'Picking List',
                'es' => 'Picking List',
                'nl' => 'Picking List',
            ],
            'class_name'        => 'AdminDfsPickingList',
            'visible'           => true,
            'parent_class_name' => 'AdminParentOrders',
            'icon'              => 'local_shipping',
        ],
    ];

    public function __construct()
    {
        $this->name                   = 'dfs_pickinglist';
        $this->tab                    = 'administration';
        $this->version                = '1.0.0';
        $this->author                 = 'Cyrille Mohr - Digital Food System';
        $this->need_instance          = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap              = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'DFS Picking List',
            [],
            'Modules.Dfspickinglist.Admin'
        );
        $this->description = $this->trans(
            'Génère une liste de préparation des produits à partir d\'un transporteur et d\'une date.',
            [],
            'Modules.Dfspickinglist.Admin'
        );
    }

    // -------------------------------------------------------------------------
    // Installation / Désinstallation
    // -------------------------------------------------------------------------

    public function install(): bool
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $this->createIndexes();

        return parent::install()
            && Configuration::updateValue('DFSPICKING_EXCLUDED_STATES', '');
    }

    public function uninstall(): bool
    {
        $this->dropIndexes();

        return parent::uninstall()
            && Configuration::deleteByName('DFSPICKING_EXCLUDED_STATES');
    }

    // -------------------------------------------------------------------------
    // Indexes de performance (DFS tables)
    // -------------------------------------------------------------------------

    /**
     * Crée les indexes de couverture sur les tables DFS pour optimiser
     * les filtres de date (évite COALESCE-based full scans).
     *
     * Utilise try/catch : si la table n'existe pas ou l'index est déjà là,
     * l'installation continue normalement.
     */
    private function createIndexes(): void
    {
        $db = Db::getInstance();

        // Index : dfs_clickcollect_creneau (id_order, day)
        try {
            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau`
                 ADD INDEX `idx_dfspl_cc_order_day` (`id_order`, `day`)'
            );
        } catch (\Exception $e) {
            // Index déjà existant ou table absente — acceptable
        }

        // Index : dfs_delivery_order (id_order, delivery_date)
        try {
            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'dfs_delivery_order`
                 ADD INDEX `idx_dfspl_dd_order_date` (`id_order`, `delivery_date`)'
            );
        } catch (\Exception $e) {
            // Index déjà existant ou table absente — acceptable
        }
    }

    /**
     * Supprime les indexes créés par ce module à la désinstallation.
     */
    private function dropIndexes(): void
    {
        $db = Db::getInstance();

        try {
            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'dfs_clickcollect_creneau`
                 DROP INDEX `idx_dfspl_cc_order_day`'
            );
        } catch (\Exception $e) {
            // Ignoré proprement
        }

        try {
            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'dfs_delivery_order`
                 DROP INDEX `idx_dfspl_dd_order_date`'
            );
        } catch (\Exception $e) {
            // Ignoré proprement
        }
    }
}
