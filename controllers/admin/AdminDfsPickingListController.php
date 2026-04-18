<?php
/**
 * DFS Picking List — Contrôleur Back-Office
 *
 * Pattern de rendu identique à AdminDfsClickCollectController (module de référence) :
 * - initContent() avec parent call
 * - fetch du template dans $this->content
 * - assign 'content' => $this->content en fin d'initContent()
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

        $this->meta_title                 = 'Picking List';
        $this->page_header_toolbar_title  = 'Picking List';
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

            if (empty($filters['mode']) || empty($filters['date_from'])) {
                $this->errors[] = 'Veuillez sélectionner un mode de livraison et une date.';
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
    // initContent — Rendu de la page (pattern identique à dfs_clickcollect)
    // -------------------------------------------------------------------------

    public function initContent()
    {
        parent::initContent();

        $idLang  = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShops = $this->getShopIds();

        // Construction des options de filtre (transporteurs + lieux C&C)
        $enricher    = new CarrierEnricher();
        $modeOptions = $enricher->buildFilterOptions($idLang, $idShops);

        // Récupération des filtres courants
        $filters = $this->getFilters();

        // Récupération des données si un filtre est appliqué
        $rows        = [];
        $showDateCol = false;
        $hasResults  = false;
        $hasFilter   = !empty($filters['mode']) && !empty($filters['date_from']);

        if ($hasFilter) {
            $service = new PickingDataService($this->context);
            $result  = $service->getPickingData($filters);

            $rows        = $result['rows'];
            $showDateCol = $result['show_date_col'];
            $hasResults  = !empty($rows);
        }

        // Lien du contrôleur (inclut token CSRF)
        $controllerUrl = $this->context->link->getAdminLink('AdminDfsPickingList');

        $this->context->smarty->assign([
            'mode_options'   => $modeOptions,
            'filters'        => $filters,
            'rows'           => $rows,
            'show_date_col'  => $showDateCol,
            'has_results'    => $hasResults,
            'has_filter'     => $hasFilter,
            'controller_url' => $controllerUrl,
            'today'          => date('Y-m-d'),
        ]);

        // Fetch du template et injection dans $this->content
        $templatePath = _PS_MODULE_DIR_ . 'dfs_pickinglist/views/templates/admin/picking_list.tpl';
        $this->content .= $this->context->smarty->fetch($templatePath);

        // CRITIQUE : assign 'content' pour que le layout PS9 l'affiche
        $this->context->smarty->assign([
            'content' => $this->content,
        ]);
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Parse et valide les filtres depuis la requête HTTP (GET ou POST).
     */
    private function getFilters(): array
    {
        $rawMode  = (string) Tools::getValue('picking_mode', '');
        $dateFrom = (string) Tools::getValue('picking_date_from', '');
        $dateTo   = (string) Tools::getValue('picking_date_to', '');

        // Validation format date ISO YYYY-MM-DD
        $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
        $dateFrom  = preg_match($dateRegex, $dateFrom) ? $dateFrom : '';
        $dateTo    = preg_match($dateRegex, $dateTo)   ? $dateTo   : '';

        // Si date_to absente → date unique (range = même jour)
        if ($dateFrom && !$dateTo) {
            $dateTo = $dateFrom;
        }

        // Parsing du mode : "carrier:7" ou "clickcollect:4"
        $parts = explode(':', $rawMode, 2);
        $type  = $parts[0] ?? '';
        $value = isset($parts[1]) ? (int) $parts[1] : 0;

        if (!in_array($type, ['carrier', 'clickcollect'], true)) {
            $type  = '';
            $value = 0;
        }

        $isRange = $dateFrom && $dateTo && ($dateFrom !== $dateTo);

        return [
            'mode'      => $rawMode,
            'type'      => $type,
            'value'     => $value,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'is_range'  => $isRange,
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
