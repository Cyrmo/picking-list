<?php
/**
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 *
 * @author    inAzerty  <contact@inazerty.com>
 * @copyright 2019 - 2021 inAzerty
 * @license   commercial
 * @version   1.11.1 from 2021/03/11
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Pspickinglist extends Module
{
    public $tabs = array(
        array(
            'name' => array(
                'en' => 'Picking list',
                'fr' => 'Picking list'
            ),
            'class_name' => 'AdminPspickinglist',
            'visible' => true,
            'parent_class_name' => 'AdminParentOrders',
        )
    );

    public static $curl_options ;

    public $available_carriers ;

    public function __construct()
    {
        $this->name = 'pspickinglist';
        $this->tab = 'administration';
        $this->version = '1.11.1';
        $this->author = 'inAzerty';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->module_key = '1ecbf257ef5832c3227d9a1f5e8de498';

        parent::__construct();

        $this->displayName = $this->l('Orders picking list');
        $this->description = $this->l('This module generates a list of products to be picked in the warehouse for current orders preparation.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $id_shop = Context::getContext()->shop->id;

        //available carriers
        $this->available_carriers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
         SELECT * 
         FROM `' . _DB_PREFIX_ . 'carrier` c
         LEFT JOIN `' . _DB_PREFIX_ . 'carrier_shop` cs ON cs.id_carrier = c.id_carrier
         WHERE c.`active` = 1
         AND c.`deleted` = 0
         AND cs.`id_shop` = ' . (int) pSQL($id_shop) .' ORDER BY c.`name`ASC'
        );

       


        //if (!Configuration::get('MYMODULE_NAME'))
        //$this->warning = $this->l('No name provided');
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        //Ajout du liens vers la page des menus existants

        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $tab = new Tab();
            $tab->class_name = 'AdminPspickinglist';
            $tab->id_parent = Tab::getIdFromClassName('AdminParentOrders');
            $tab->module = $this->name;
            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $this->l('Picking list');
            }
            try {
                $tab->save();
            } catch (Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }

        $available_carriers_ids = [];
        $available_carriers_ids = array_map(
            function ($e) {
                return $e['id_carrier'] ;
            },
            $this->available_carriers
        );

        /**
         * @since 1/10
         */
        self::testCurl();

        if (!Configuration::updateValue('PSPICKINGLIST_MODE', 'PDF')
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_IMAGE', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_ID', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_REF', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_EAN13', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_BARCODE', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_STOCK_QUANTITY', 0)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY', 0)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER', 1)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY', 0)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF', 0)
            || !Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_LOCATION', 0)
            || !Configuration::updateValue('PSPICKINGLIST_ORDER_REF_MODE', 'order_id')
            || !Configuration::updateValue('PSPICKINGLIST_PRODUCTS_SORTING', 'manufacturer_asc')
            || !Configuration::updateValue('PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY', 'alpha_asc')
            || !Configuration::updateValue('PSPICKINGLIST_ORDER_STATE', 2)
            || !Configuration::updateValue('PSPICKINGLIST_ORDER_CARRIERS', implode(',', $available_carriers_ids))
            || !Configuration::updateValue('PSPICKINGLIST_PACK_BEHAVIOUR', 'standard')
            || !Configuration::updateValue('PSPICKINGLIST_CURL_USERAGENT', 0)
            || !Configuration::updateValue('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE', 0)
            || !Configuration::updateValue('PSPICKINGLIST_UPDATE_ORDERS_STATE', 'none')
        ) {
            return false ;
        }
  

        return parent::install();
    }

   

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        //$idTab = (int)Tab::getIdFromClassName('AdminPspickinglist');

        $idTab = Db::getInstance()->getValue("SELECT id_tab FROM "._DB_PREFIX_."tab WHERE class_name='AdminPspickinglist'");


        if ($idTab) {
            $tab = new Tab($idTab);
            try {
                $tab->delete();
            } catch (Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }

        //$this->clearCache() ;

        return true;
    }


    public function displayForm()
    {
        
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        //order states
        $states = OrderState::getOrderStates($default_lang);
        $states_for_select = [];
        foreach ($states as $state) {
            $states_for_select[] = [
                'key' => $state['id_order_state'],
                'name' => $state['name'],
            ];
        }

        $none =  [ // to be unshift at $states_for_select array top
            [
            'key' => 'none',
            'name' => $this->l('No change'),
            ]
        ];

        $states_for_select_with_none = $none + $states_for_select; // states with "none" value at begining

        //orders shioppe bay carriers
        $available_carriers_for_select = [];
        foreach ($this->available_carriers as $available_carrier) {
            $available_carriers_for_select[] = [
                'key' => $available_carrier['id_carrier'],
                'name' => $available_carrier['name'],
            ];
        }

        /**
         * @since 1.11.1
         */
        $available_order_ref_mode = [
           
            [
                'key' => 'order_id',
                'name' => $this->l('Order ID')
            ],
            [
                'key' => 'order_ref',
                'name' => $this->l('Order Reference')
            ],
            [
                'key' => 'order_customer',
                'name' => $this->l('Customer name (from delivery address)')
            ],
            [
                'key' => 'order_id_customer',
                'name' => $this->l('Order ID + Customer name (from delivery address)')
            ],
            [
                'key' => 'order_ref_customer',
                'name' => $this->l('Order Reference + Customer name (from delivery address)')
            ],
            [
                'key' => 'hide',
                'name' => $this->l('Do not show this column')
            ]
        ];


        

        // Init Fields form array
        $fields_form = [];

        $fields_form[0]['form'] = array(
        'legend' => array(
            'title' => $this->l('Output'),
        ),
        'input' => array(
           
            array(
                'type' => 'select',
                'label' => $this->l('Output mode'),
                'name' => 'PSPICKINGLIST_MODE',
                'id' => 'PSPICKINGLIST_MODE',
                'desc' => $this->l('PDF can contains shop logo, products image and barcode. CSV contains only text, and generates faster (usefull for large amount of orders). CSV is semi-colon ";" separated.'),
                'required' => true,
                'multiple' => false,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => [
                        [
                            'key' => 'PDF',
                            'name' => 'PDF',
                        ],
                        [
                            'key' => 'CSV',
                            'name' => 'CSV',
                        ],
                        [
                            'key' => 'CSV_excel',
                            'name' => $this->l('CSV for MS Excel'),
                        ],
                    ] ,
                    'id' => 'key',
                    'name' => 'name',
                   
                ),
            )
        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );


        $fields_form[1]['form'] = array(
        'legend' => array(
            'title' => $this->l('Define orders that you want to include in the list (states and carriers):'),
        ),
        'input' => array(
           
            array(
                'type' => 'select',
                'label' => $this->l('Order state(s)'),
                'name' => 'PSPICKINGLIST_ORDER_STATE[]',
                'id' => 'PSPICKINGLIST_ORDER_STATE',
                'desc' => $this->l('List will contain products from all orders with this state.'),
                'required' => true,
                'multiple' => true,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => $states_for_select ,
                    'id' => 'key',
                    'name' => 'name',
                  
                   
                ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Carrier(s)'),
                'name' => 'PSPICKINGLIST_ORDER_CARRIERS[]',
                'id' => 'PSPICKINGLIST_ORDER_CARRIERS',
                'desc' => $this->l('List will contain products from all orders shipped by selected carriers.'),
                'required' => true,
                'multiple' => true,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => $available_carriers_for_select ,
                    'id' => 'key',
                    'name' => 'name',
                  
                   
                ),
            ),
          

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );



        $fields_form[2]['form'] = array(
        'legend' => array(
            'title' => $this->l('Define infos to be included in the list:'),
        ),
        'input' => array(
           
           
            array(
                'type' => 'select',
                'label' => $this->l('List ordering'),
                'name' => 'PSPICKINGLIST_PRODUCTS_SORTING',
                'id' => 'PSPICKINGLIST_PRODUCTS_SORTING',
                'desc' => $this->l('Choose sorting way for products. Default : "Manufacturer name ASC". Variations (if exist) are grouped by product ID).'),
                'required' => true,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => [
                            array(
                                'key' => 'manufacturer_asc',
                                'name' => $this->l('Manufacturer name alphabetical ASC'),
                            ),
                            array(
                                'key' => 'manufacturer_desc',
                                'name' => $this->l('Manufacturer name alphabetical DESC'),
                            ),
                            array(
                                'key' => 'count_desc',
                                'name' => $this->l('Products count DESC'),
                            ),
                            array(
                                'key' => 'count_asc',
                                'name' => $this->l('Products count ASC'),
                            ),
                            array(
                                'key' => 'alpha_asc',
                                'name' => $this->l('Product name alphabetical ASC'),
                            ),
                            array(
                                'key' => 'alpha_desc',
                                'name' => $this->l('Product name alphabetical DESC'),
                            ),
                            array(
                                'key' => 'category_asc',
                                'name' => $this->l('Category name alphabetical ASC'),
                            ),
                            array(
                                'key' => 'category_desc',
                                'name' => $this->l('Category name alphabetical DESC'),
                            ),
                            array(
                                'key' => 'location_asc',
                                'name' => $this->l('Product location alphabetical ASC'),
                            ),
                            array(
                                'key' => 'location_desc',
                                'name' => $this->l('Product location alphabetical DESC'),
                            ),
                           
                           
                     ],
                    'id' => 'key',
                    'name' => 'name',
                  
                   
                ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Secondary list ordering'),
                'name' => 'PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY',
                'id' => 'PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY',
                'desc' => $this->l('Sorting way inside primary sorting, available only if primary sorting is by manufacturer or category. ie: Sort by manufacturer, then sort sublist by product name alphabetical'),
                'required' => false,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => [
                        
                            array(
                                'key' => 'manufacturer_asc',
                                'name' => $this->l('Manufacturer name alphabetical ASC'),
                            ),
                            // array(
                            //     'key' => 'manufacturer_desc',
                            //     'name' => $this->l('Manufacturer name alphabetical DESC'),
                            // ),
                            // array(
                            //     'key' => 'count_desc',
                            //     'name' => $this->l('Products count DESC'),
                            // ),
                            // array(
                            //     'key' => 'count_asc',
                            //     'name' => $this->l('Products count ASC'),
                            // ),
                            array(
                                'key' => 'alpha_asc',
                                'name' => $this->l('Product name alphabetical ASC'),
                            ),
                            // array(
                            //     'key' => 'alpha_desc',
                            //     'name' => $this->l('Product name alphabetical DESC'),
                            // ),
                            array(
                                'key' => 'category_asc',
                                'name' => $this->l('Category name alphabetical ASC'),
                            ),
                            // array(
                            //     'key' => 'category_desc',
                            //     'name' => $this->l('Category name alphabetical DESC'),
                            // ),
                            // array(
                            //     'key' => 'location_asc',
                            //     'name' => $this->l('Product location alphabetical ASC'),
                            // ),
                            // array(
                            //     'key' => 'location_desc',
                            //     'name' => $this->l('Product location alphabetical DESC'),
                            // ),
                           
                           
                     ],
                    'id' => 'key',
                    'name' => 'name',
                  
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product image?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_IMAGE',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_IMAGE',
                'desc' => $this->l('Display product image in first column of each line.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product ID?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_ID',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_ID',
                'desc' => $this->l('Display product ID before product name.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product reference?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_REF',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_REF',
                'desc' => $this->l('Display product ref.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product EAN-13/JAN?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_EAN13',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_EAN13',
                'desc' => $this->l('Display product EAN-13 or JAN code.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product barcode?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_BARCODE',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_BARCODE',
                'desc' => $this->l('Barcode is generated from a valid 12/13 digits EAN-13 code.'),
                'required' => true,
                'disabled' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show remaining quantity in stock?'),
                'name' => 'PSPICKINGLIST_SHOW_STOCK_QUANTITY',
                'id' => 'PSPICKINGLIST_SHOW_STOCK_QUANTITY',
                'desc' => $this->l('Display remaining quantity in stock after picking (Allows to check the stock of your products regularly. Improves inventory management).'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show physical quantity in stock?'),
                'name' => 'PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY',
                'id' => 'PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY',
                'desc' => $this->l('Display physical quantity in stock before picking (Allows to check the stock of your products regularly. Improves inventory management). PS >= 1.7 only'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product category?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_CATEGORY',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_CATEGORY',
                'desc' => $this->l('Display product category name.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product manufacturer?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER',
                'desc' => $this->l('Display product manufacturer name.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product supplier ref?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF',
                'desc' => $this->l('Display product supplier reference.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show product location?'),
                'name' => 'PSPICKINGLIST_SHOW_PRODUCT_LOCATION',
                'id' => 'PSPICKINGLIST_SHOW_PRODUCT_LOCATION',
                'desc' => $this->l('Display product location in warehouse (only if information exists, might depends on your PS version).'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Order/Customer reference'),
                'name' => 'PSPICKINGLIST_ORDER_REF_MODE',
                'id' => 'PSPICKINGLIST_ORDER_REF_MODE',
                'desc' => $this->l('Show associated order or customer, with count of product items between parenthesis.'),
                'required' => true,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => $available_order_ref_mode,
                    'id' => 'key',
                    'name' => 'name',
                ),
                'default' => 'none'
            ),
          

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );


        $fields_form[3]['form'] = array(
        'legend' => array(
            'title' => $this->l('Automatically update orders states:'),
        ),
        'input' => array(
           
           
           
            array(
                'type' => 'switch',
                'label' => $this->l('Update orders state once pickinglist is generated?'),
                'name' => 'PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE',
                'id' => 'PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE',
                'desc' => $this->l('States of orders included in the pickinglist can be bulk updated on pickinglist generation. Please note that this option can slow the pickinglist generation depending on your server power and amount of orders/products included.'),
                'required' => true,
                'values' => array(
                    array(
                        'value' => '1',
                        'label' => $this->l('yes'),
                    ),
                    array(
                        'value' => '0',
                        'label' => $this->l('no'),
                    ),
                   
                ),
                'default' => 0
            ),

            array(
                'type' => 'select',
                'label' => $this->l('New orders state'),
                'name' => 'PSPICKINGLIST_UPDATE_ORDERS_STATE',
                'id' => 'PSPICKINGLIST_UPDATE_ORDERS_STATE',
                'desc' => $this->l('Orders state will be changed to selected state'),
                'required' => false,
                'disabled' => true,
                'multiple' => false,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => $states_for_select_with_none,
                    'id' => 'key',
                    'name' => 'name',
                ),
                'default' => 'none'
            ),
           
          

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );


        $fields_form[4]['form'] = array(
        'legend' => array(
            'title' => $this->l('Packs behaviour:'),
        ),
        'input' => array(
           
            array(
                'type' => 'select',
                'label' => $this->l('How to deal with packs?'),
                'name' => 'PSPICKINGLIST_PACK_BEHAVIOUR',
                'id' => 'PSPICKINGLIST_PACK_BEHAVIOUR',
                'desc' => $this->l('Packs can be handled as standard products in the list, or can be exploded to display each item of the pack.'),
                'required' => true,
                'multiple' => false,
                'class' => 'fixed-width-xxl',
                'options' => array(
                    'query' => [
                        [
                            'key' => 'standard',
                            'name' => $this->l('Display packs as standard products'),
                        ],
                        [
                            'key' => 'explode',
                            'name' => $this->l('Explode and display products individually'),
                        ],
                    ] ,
                    'id' => 'key',
                    'name' => 'name',
                   
                ),
                'default' => 'none'
            ),
           
          

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
        'save' =>
        array(
            'desc' => $this->l('Save'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
            '&token='.Tools::getAdminTokenLite('AdminModules'),
        ),
        'back' => array(
            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        )
        );

        // Load current value
        $helper->fields_value['PSPICKINGLIST_MODE'] = Configuration::get('PSPICKINGLIST_MODE');
        $helper->fields_value['PSPICKINGLIST_PACK_BEHAVIOUR'] = Configuration::get('PSPICKINGLIST_PACK_BEHAVIOUR');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_IMAGE'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_IMAGE');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_ID'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_ID');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_REF'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_REF');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_EAN13'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_EAN13');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_BARCODE'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_BARCODE');
        $helper->fields_value['PSPICKINGLIST_SHOW_STOCK_QUANTITY'] = Configuration::get('PSPICKINGLIST_SHOW_STOCK_QUANTITY');
        $helper->fields_value['PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY'] = Configuration::get('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_CATEGORY'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF');
        $helper->fields_value['PSPICKINGLIST_SHOW_PRODUCT_LOCATION'] = Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_LOCATION');
        $helper->fields_value['PSPICKINGLIST_ORDER_REF_MODE'] = Configuration::get('PSPICKINGLIST_ORDER_REF_MODE');
        $helper->fields_value['PSPICKINGLIST_PRODUCTS_SORTING'] = Configuration::get('PSPICKINGLIST_PRODUCTS_SORTING');
        $helper->fields_value['PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY'] = Configuration::get('PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY');
        $helper->fields_value['PSPICKINGLIST_ORDER_STATE[]'] = explode(',', Configuration::get('PSPICKINGLIST_ORDER_STATE'));
        $helper->fields_value['PSPICKINGLIST_ORDER_CARRIERS[]'] = explode(',', Configuration::get('PSPICKINGLIST_ORDER_CARRIERS'));
        $helper->fields_value['PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE'] = Configuration::get('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE');
        $helper->fields_value['PSPICKINGLIST_UPDATE_ORDERS_STATE'] = Configuration::get('PSPICKINGLIST_UPDATE_ORDERS_STATE');

        return $helper->generateForm($fields_form);
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $pspickinglist_mode = Tools::getValue('PSPICKINGLIST_MODE');
            $pspickinglist_pack_behaviour = Tools::getValue('PSPICKINGLIST_PACK_BEHAVIOUR');
            $pspickinglist_show_product_image = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_IMAGE');
            $pspickinglist_show_product_id = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_ID');
            $pspickinglist_show_product_ref = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_REF');
            $pspickinglist_show_product_ean13 = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_EAN13');
            $pspickinglist_show_product_barcode = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_BARCODE');
            $pspickinglist_show_stock_quantity = Tools::getValue('PSPICKINGLIST_SHOW_STOCK_QUANTITY');
            $pspickinglist_show_stock_physical_quantity = Tools::getValue('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY');
            $pspickinglist_show_product_category = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY');
            $pspickinglist_show_product_manufacturer = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER');
            $pspickinglist_show_product_supplier_ref = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF');
            $pspickinglist_show_product_location = Tools::getValue('PSPICKINGLIST_SHOW_PRODUCT_LOCATION');
            $pspickinglist_order_ref_mode = Tools::getValue('PSPICKINGLIST_ORDER_REF_MODE');
            $pspickinglist_products_sorting = Tools::getValue('PSPICKINGLIST_PRODUCTS_SORTING');
            $pspickinglist_products_sorting_secondary = Tools::getValue('PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY');
            $pspickinglist_order_state= implode(',', Tools::getValue('PSPICKINGLIST_ORDER_STATE'));
            $pspickinglist_order_carriers= implode(',', Tools::getValue('PSPICKINGLIST_ORDER_CARRIERS'));
            $pspickinglist_switch_update_orders_state = Tools::getValue('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE');
            $pspickinglist_update_orders_state = Tools::getValue('PSPICKINGLIST_UPDATE_ORDERS_STATE');
           
         
            Configuration::updateValue('PSPICKINGLIST_MODE', $pspickinglist_mode);
            Configuration::updateValue('PSPICKINGLIST_PACK_BEHAVIOUR', $pspickinglist_pack_behaviour);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_IMAGE', $pspickinglist_show_product_image);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_ID', $pspickinglist_show_product_id);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_REF', $pspickinglist_show_product_ref);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_EAN13', $pspickinglist_show_product_ean13);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_BARCODE', $pspickinglist_show_product_barcode);
            Configuration::updateValue('PSPICKINGLIST_SHOW_STOCK_QUANTITY', $pspickinglist_show_stock_quantity);
            Configuration::updateValue('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY', $pspickinglist_show_stock_physical_quantity);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY', $pspickinglist_show_product_category);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER', $pspickinglist_show_product_manufacturer);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF', $pspickinglist_show_product_supplier_ref);
            Configuration::updateValue('PSPICKINGLIST_SHOW_PRODUCT_LOCATION', $pspickinglist_show_product_location);
            Configuration::updateValue('PSPICKINGLIST_ORDER_REF_MODE', $pspickinglist_order_ref_mode);
            Configuration::updateValue('PSPICKINGLIST_PRODUCTS_SORTING', $pspickinglist_products_sorting);
            Configuration::updateValue('PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY', $pspickinglist_products_sorting_secondary);
            Configuration::updateValue('PSPICKINGLIST_ORDER_STATE', $pspickinglist_order_state);
            Configuration::updateValue('PSPICKINGLIST_ORDER_CARRIERS', $pspickinglist_order_carriers);
            Configuration::updateValue('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE', $pspickinglist_switch_update_orders_state);
            Configuration::updateValue('PSPICKINGLIST_UPDATE_ORDERS_STATE', $pspickinglist_update_orders_state);
                
            $output .= $this->displayConfirmation($this->l('Settings updated'));

            //$this->clearCache() ;
        }
        if (defined('_PS_ADMIN_DIR_')) {
            $this->admin_webpath = str_ireplace(_PS_CORE_DIR_, '', _PS_ADMIN_DIR_);
            $this->admin_webpath = preg_replace('/^'.preg_quote(DIRECTORY_SEPARATOR, '/').'/', '', $this->admin_webpath);
        }
     
        $this->context->controller->addCSS(__PS_BASE_URI__.$this->admin_webpath.'/themes/new-theme/public/theme.css', 'all', 1);
        $this->context->controller->addJS(_PS_MODULE_DIR_.$this->name.'/views/js/back.js', 1);

        $this->context->smarty->assign(
            array(
            'name' => $this->name,
            'admin_link' => $this->context->link->getAdminLink('AdminPspickinglist'),
            'version' => $this->version,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'module_dir' => $this->_path,
            'curl_enabled' => (bool) function_exists('curl_version'),
            'shop_enabled' => self::checkShopIsActivated(),
            'base_url' => _PS_BASE_URL_,
            'local_ip' => getHostByName(getHostName())
            )
        );


        $config_header = $this->context->smarty->fetch($this->local_path.'views/templates/admin/config_header.tpl');
        $config_footer = $this->context->smarty->fetch($this->local_path.'views/templates/admin/config_footer.tpl');
        return $config_header.$output.$this->displayForm().$config_footer;
    }
    

    private function clearCache()
    {
        $this->_clearCache('pspickinglist.tpl');
        $this->_clearCache('products-list.tpl');
    }

    public static function getCurlOptions($is_test = false, $query_string = [])
    {
        if (!function_exists('curl_version')) {
            return ;
        }
        
        $url = $is_test ? _PS_BASE_URL_.__PS_BASE_URI__ : Context::getContext()->link->getModuleLink('pspickinglist', 'Renderpdf') ;
        $force_curl_user_agent = (bool) Configuration::get('PSPICKINGLIST_CURL_USERAGENT') ;


        return array(
             CURLOPT_URL => $url,
             CURLOPT_RETURNTRANSFER => true,
             CURLOPT_ENCODING => "",
             CURLOPT_MAXREDIRS => 10,
             CURLOPT_TIMEOUT => 0,
             CURLOPT_FOLLOWLOCATION => true,
             CURLOPT_USERAGENT => false == $force_curl_user_agent ? null : 'Mozilla/5.0 (Windows; U; Windows NT 6.1; fr; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13',
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
             CURLOPT_CUSTOMREQUEST => "POST",
             CURLOPT_POSTFIELDS => $query_string,
             CURLOPT_HTTPHEADER => array(
                 "Content-Type: application/x-www-form-urlencoded",
             ),
         );
    }

    /**
     * @since 1.11.1
     * Test if curl is working fine on install
     * Add a User Agent option if don't work
     */
    public static function testCurl($is_test = true)
    {
        if (!function_exists('curl_version')) {
            return ;
        }

        $curl = curl_init();

        $curl_options = self::getCurlOptions($is_test);
     
        curl_setopt_array(
            $curl,
            $curl_options
        );
       
        $response = curl_exec($curl);
        curl_close($curl);

        // If $response = false, it could be caused by the server that need a user agent in the curl request
        if (false == $response) {
            Configuration::updateValue('PSPICKINGLIST_CURL_USERAGENT', 1) ;
           // self::testCurl(true);
        }
    }


    /**
     * @since 1.10
     * Check is shop is activated or not activated but local server IP whitelisted
     * @return bool
     */
    public static function checkShopIsActivated()
    {
        $is_activated = true ; // default true
        $config_activated = (bool) Configuration::get('PS_SHOP_ENABLE') ;
        // If maintenance mode, check if local IP is whitelisted
        if (!$config_activated) {
            $is_activated = false ;
            $allowed_ips = explode(',', Configuration::get('PS_MAINTENANCE_IP')) ;
            if (in_array('127.0.0.1', $allowed_ips)) {
                $is_activated = true ;
            }
        }

        return $is_activated ;
    }

    /**
     * Get the DDW Date and Time for a specific order
     * @param id_order
     */
    public static function getOrderDDW($id_order)
    {
        $ddw_order = [
            'ddw_order_date' => '0000-00-00 00:00:00',
            'ddw_order_time' =>null
        ];
        
        if (Module::isEnabled('deliverydateswizard') || Module::isEnabled('deliverydateswizardpro')) {
            $sql = new DbQuery();
            $sql->select('ddw_order_date, ddw_order_time');
            $sql->from('orders');
            $sql->where('id_order = '.(int)$id_order);
    
            $row = Db::getInstance()->getRow($sql);
    
            if (!empty($row) && is_array($row)) {
                $ddw_order['ddw_order_date'] = $row['ddw_order_date'];
                $ddw_order['ddw_order_time'] = $row['ddw_order_time'];
            }
        }
        if (Module::isEnabled('fspickupatstorecarrier')) {
            $sql = new DbQuery();
            $sql->select('date_pickup');
            $sql->from('fspickupatstorecarrier');
            $sql->where('id_order = '.(int)$id_order);

            $result = Db::getInstance()->getValue($sql);

    
            if (false !== $result && !empty($result)) {
                $ddw_order['ddw_order_date'] = $result;
            }
        }
       
        return $ddw_order;
    }
}
