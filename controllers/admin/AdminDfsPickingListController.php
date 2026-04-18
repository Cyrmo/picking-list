<?php
/**
 * DFS Picking List — Contrôleur Back-Office
 *
 * v1.1.0 — état de commande + filtres mode OU
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 * @copyright 2024-2026 Digital Food System
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminDfsPickingListController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;

        parent::__construct();

        if (!$this->module) {
            $this->module = Module::getInstanceByName('dfs_pickinglist');
        }

        $this->meta_title                = 'Picking List';
        $this->page_header_toolbar_title = 'Picking List';
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = 'Picking List';
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = 'Picking List';
        $this->context->smarty->assign('page_header_toolbar_title', 'Picking List');
    }

    // -------------------------------------------------------------------------
    // postProcess — Gestion des exports (CSV, XLSX, PDF)
    // -------------------------------------------------------------------------

    public function postProcess()
    {
        $isExport = Tools::isSubmit('export_csv')
            || Tools::isSubmit('export_xlsx')
            || Tools::isSubmit('export_pdf');

        if ($isExport) {
            $filters = $this->getFilters();

            if (!$filters['has_filter']) {
                $this->errors[] = 'Veuillez sélectionner au moins un critère.';
                return;
            }

            $service = new PickingDataService($this->context);
            $result  = $service->getPickingData($filters);

            if (Tools::isSubmit('export_csv')) {
                (new CsvExporter())->export($result['rows'], $filters);
            } elseif (Tools::isSubmit('export_xlsx')) {
                (new XlsxExporter())->export($result['rows'], $filters);
            } elseif (Tools::isSubmit('export_pdf')) {
                (new PdfExporter())->export($result['rows'], $filters);
            }

            exit;
        }

        parent::postProcess();
    }

    // -------------------------------------------------------------------------
    // initContent — Rendu de la page (pattern dfs_clickcollect)
    // -------------------------------------------------------------------------

    public function initContent()
    {
        parent::initContent();

        $idLang  = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShops = $this->getShopIds();

        // Options du filtre "Mode de livraison"
        $enricher    = new CarrierEnricher();
        $modeOptions = $enricher->buildFilterOptions($idLang, $idShops);

        // États de commande disponibles (pour le multi-select)
        $orderStates = OrderState::getOrderStates($idLang);

        // Filtres courants
        $filters = $this->getFilters();

        // Données si au moins un filtre actif
        $rows        = [];
        $showDateCol = false;
        $hasResults  = false;

        if ($filters['has_filter']) {
            $service = new PickingDataService($this->context);
            $result  = $service->getPickingData($filters);

            $rows        = $result['rows'];
            $showDateCol = $result['show_date_col'];
            $hasResults  = !empty($rows);
        }

        $controllerUrl = $this->context->link->getAdminLink('AdminDfsPickingList');

        $this->context->smarty->assign([
            'mode_options'   => $modeOptions,
            'order_states'   => $orderStates,
            'filters'        => $filters,
            'rows'           => $rows,
            'show_date_col'  => $showDateCol,
            'has_results'    => $hasResults,
            'has_filter'     => $filters['has_filter'],
            'controller_url' => $controllerUrl,
            'today'          => date('Y-m-d'),
            'token'          => Tools::getAdminTokenLite('AdminDfsPickingList'),
        ]);

        $templatePath = _PS_MODULE_DIR_ . 'dfs_pickinglist/views/templates/admin/picking_list.tpl';
        $this->content .= $this->context->smarty->fetch($templatePath);

        $this->context->smarty->assign([
            'content' => $this->content,
        ]);
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Parse et valide les filtres depuis la requête HTTP.
     *
     * Filtre principal : formulaire GET (pas de token POST requis)
     * Exports : formulaires POST séparés (token dans l'URL de l'action)
     *
     * Tools::getValue() lit GET puis POST — compatible avec les deux.
     *
     * Évolution v1.1 :
     * - Ajout du filtre multi-états (picking_states[])
     * - has_filter = true si AU MOINS UN critère est renseigné
     */
    private function getFilters(): array
    {
        // --- Mode de livraison ---
        $rawMode  = (string) Tools::getValue('picking_mode', '');
        $parts    = explode(':', $rawMode, 2);
        $type     = $parts[0] ?? '';
        $value    = isset($parts[1]) ? (int) $parts[1] : 0;

        if (!in_array($type, ['carrier', 'clickcollect'], true)) {
            $type  = '';
            $value = 0;
        }

        // --- Dates ---
        $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
        $dateFrom  = (string) Tools::getValue('picking_date_from', '');
        $dateTo    = (string) Tools::getValue('picking_date_to', '');

        $dateFrom = preg_match($dateRegex, $dateFrom) ? $dateFrom : '';
        $dateTo   = preg_match($dateRegex, $dateTo)   ? $dateTo   : '';

        if ($dateFrom && !$dateTo) {
            $dateTo = $dateFrom;
        }

        $isRange = $dateFrom && $dateTo && ($dateFrom !== $dateTo);

        // --- États de commande (multi-select) ---
        $rawStates = Tools::getValue('picking_states', []);
        if (!is_array($rawStates)) {
            $rawStates = [];
        }
        $states = array_values(array_filter(array_map('intval', $rawStates)));

        // --- Logique mode OU : au moins un critère ---
        $hasMode   = !empty($rawMode) && !empty($type);
        $hasDate   = !empty($dateFrom);
        $hasStates = !empty($states);
        $hasFilter = $hasMode || $hasDate || $hasStates;

        return [
            // Mode de livraison
            'mode'      => $rawMode,
            'type'      => $type,
            'value'     => $value,
            // Dates
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'is_range'  => $isRange,
            // États
            'states'    => $states,
            // Méta logique
            'has_mode'   => $hasMode,
            'has_date'   => $hasDate,
            'has_states' => $hasStates,
            'has_filter' => $hasFilter,
        ];
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
