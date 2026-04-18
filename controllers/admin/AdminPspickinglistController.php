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

require_once _PS_MODULE_DIR_ . 'pspickinglist/classes/Pspickinglistdata.php';
require_once _PS_MODULE_DIR_ . 'pspickinglist/classes/ExportCSV.php';

class AdminPspickinglistController extends ModuleAdminController
{
    protected $orders_data ;
    protected $employee ;

    public function __construct()
    {
        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $this->bootstrap = true;
        }
            
        $this->optionTitle = 'Picking list';
        $this->context = Context::getContext();

        $this->is_ddw_enabled = Module::isEnabled('deliverydateswizard') || Module::isEnabled('deliverydateswizardpro');
        $this->is_fspickupatstorecarrier_enabled = Module::isEnabled('fspickupatstorecarrier') ;

        $this->is_psd_enabled = Module::isEnabled('prestatilldrive');
        $this->is_prestatilldrive_enabled = Module::isEnabled('prestatilldrive') ;

        $this->is_phd_enabled = Module::isEnabled('prestatillhomedelivery');
        $this->is_prestatillhd_enabled = Module::isEnabled('prestatillhomedelivery') ;

        parent::__construct();
    }

    public function postProcess()
    {

        // var_dump($_POST);
        // die();
        if (Tools::isSubmit('generate_pickinglist')) {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $id_employee = Tools::getValue('id_employee');
            $orders_ids = Tools::getValue('orders_ids');
            $switch_new_orders_state = Configuration::get('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE');
            $new_orders_state = Configuration::get('PSPICKINGLIST_UPDATE_ORDERS_STATE');

            $employee = new Employee($id_employee, $id_lang);
            $pickinglistdata = new Pspickinglistdata($orders_ids); // construct Pickinglist data
            $this->orders_data = $pickinglistdata->getData();
            $this->orders_data['employee'] = $employee ;

            $shop_context = Context::getContext()->shop->getContext();

            if ($switch_new_orders_state  // Update orders status if option is set
                && !empty($new_orders_state)
                && $new_orders_state != 'none'
            ) {
                foreach ($orders_ids as $order_id) {
                    $history = new OrderHistory();
                    $history->id_order = (int)$order_id;
                    $history->id_employee = (int)$id_employee;
                    $history->changeIdOrderState((int) $new_orders_state, (int)$order_id);
                    $history->addWithemail();
                }
            }

            if (Tools::isSubmit('exportCSV')) { // CSV output
                $csv = new ExportCSV();
                $csv->export($this->orders_data['products']);
            } elseif (Tools::isSubmit('exportPDF')) { // PDF Output
                $query_string = http_build_query(
                    [
                        'orders_ids' =>$orders_ids,
                        'id_employee' => $id_employee,
                        'shop_context' => $shop_context
                    ]
                );


                $curl = curl_init();

                $curl_options = Pspickinglist::getCurlOptions(false, $query_string);

                curl_setopt_array(
                    $curl,
                    $curl_options
                );

                $response = curl_exec($curl);
                curl_close($curl);



                header('Cache-Control: public');
                header('Content-type: application/pdf');
                header('Content-Disposition: attachment; filename="pickinglist.pdf"');
                echo $response ;
                die();
            }
        } elseif (Tools::isSubmit('generate_invoices')) {
            $orders_ids = Tools::getValue('orders_ids');
            $this->processGenerateInvoicesPDF($orders_ids);
        } elseif (Tools::isSubmit('generate_delivery_slips')) {
            $orders_ids = Tools::getValue('orders_ids');
            $this->processGenerateDeliverySlipsPDF($orders_ids);
        } else {
            parent::postProcess();
        }
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['configure'] = array(
            'href' => $this->context->link->getAdminLink('AdminModules').'&configure=pspickinglist',
            'desc' => $this->module->l('Configuration'),
            'icon' => 'process-icon-configure'
        );

        parent::initPageHeaderToolbar();
    }

    public function renderList()
    {

        //action link (depends on PDF or CSV output)
        $output_mode = Configuration::get('PSPICKINGLIST_MODE');
        $controller_link = $this->context->link->getAdminLink('AdminPspickinglist');
        $action_link = $controller_link.'&' ;
        $action_link .= (false !== stripos($output_mode, 'CSV')) ? 'exportCSV' : 'exportPDF' ;

        $order_state = Configuration::get('PSPICKINGLIST_ORDER_STATE');
        $order_carriers = Configuration::get('PSPICKINGLIST_ORDER_CARRIERS');
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $weight_unit = Configuration::get('PS_WEIGHT_UNIT');
        $weight_unit = Tools::strtolower($weight_unit);
        $weight_unit_display = $weight_unit ;
        $id_shop = $this->context->shop->id;
        $id_shop_group = $this->context->shop->id_shop_group;
        $id_shops = false ; // no multistore by default

        $shop_context = Context::getContext()->shop->getContext();

        if ($shop_context == Shop::CONTEXT_ALL) {
            // get all shops
            $sql = 'SELECT id_shop FROM '._DB_PREFIX_.'shop';
            $id_shops = array_column(Db::getInstance()->ExecuteS($sql), 'id_shop');
        }
        if ($shop_context == Shop::CONTEXT_GROUP) {
            // get all shops from current group
            $sql = 'SELECT id_shop FROM '._DB_PREFIX_.'shop WHERE id_shop_group = '.$id_shop_group;
            $id_shops = array_column(Db::getInstance()->ExecuteS($sql), 'id_shop');
        }


        $states = OrderState::getOrderStates($id_lang);
        $state_shipping =Configuration::get('PS_OS_SHIPPING');

        $switch_new_orders_state =  Configuration::get('PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE');
        $new_orders_state =  Configuration::get('PSPICKINGLIST_UPDATE_ORDERS_STATE');
        $new_orders_state_name =  $this->l('no change'); // default

        //get all cariers with same reference
        $_order_carriers = explode(',', $order_carriers);

        $available_carrier_all = array();
        $_available_carrier_all = "";

        // Third party addons compatibility
       

        foreach ($_order_carriers as $carrier_id) {
            $_carrier = new Carrier($carrier_id, $id_lang);

            $available_carrier_all[] = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
                '
            SELECT `id_carrier` 
            FROM `' . _DB_PREFIX_ . 'carrier`
            WHERE `id_reference` = ' .  ((int) pSQL($_carrier->id_reference)) . ''
            );
        }

        foreach ($available_carrier_all as $id_carrier) {
            foreach ($id_carrier as $el) {
                $_available_carrier_all .= $el['id_carrier'].',' ;
            }
        }

        // name of selected order states
        $order_states_names = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
            '
		SELECT `name` 
		FROM `' . _DB_PREFIX_ . 'order_state_lang`
		WHERE `id_order_state` IN (' .  (pSQL($order_state)) . ') 
        AND `id_lang` = ' . (int) (pSQL($id_lang))
        );

        //name of selected carriers
        $order_carriers_names = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS(
            '
		SELECT `name` 
		FROM `' . _DB_PREFIX_ . 'carrier`
        WHERE `id_carrier` IN (' .  (pSQL($order_carriers)) . ') '
        );
        
        //name of new orders state if ≠ 'none'
        if ($new_orders_state && is_numeric($new_orders_state)) {
            $new_orders_state_name = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
            SELECT `name` 
            FROM `' . _DB_PREFIX_ . 'order_state_lang`
            WHERE `id_order_state` = '.$new_orders_state.' 
            AND `id_lang` = ' . (int) (pSQL($id_lang))
            );
        }

      
        $date_from = $date_to = $ddw_date_from = $ddw_date_to = false ;
        if (Tools::getValue('pspickinglist_from_alt')) {
            $date_from =Tools::getValue('pspickinglist_from_alt');
        }
        if (Tools::getValue('pspickinglist_to_alt')) {
            $date_to =Tools::getValue('pspickinglist_to_alt');
        }
        if (Tools::getValue('pspickinglist_ddw_from_alt')) {
            $ddw_date_from =Tools::getValue('pspickinglist_ddw_from_alt');
        }
        if (Tools::getValue('pspickinglist_ddw_to_alt')) {
            $ddw_date_to =Tools::getValue('pspickinglist_ddw_to_alt');
        }


        // Orders list Query
        $select_order_list = 'SELECT o.id_order FROM ' . _DB_PREFIX_ . 'orders o';

        // if fspickupatstorecarrier
        if ($this->is_fspickupatstorecarrier_enabled) {
            $select_order_list .= ' LEFT JOIN '. _DB_PREFIX_ . 'fspickupatstorecarrier fsp ON o.id_order = fsp.id_order' ;
        }

        if ($this->is_prestatilldrive_enabled) {
            $select_order_list .= ' LEFT JOIN '. _DB_PREFIX_ . 'prestatill_drive_creneau psd ON o.id_order = psd.id_order' ;
        }

        if ($this->is_prestatillhd_enabled) {
            $select_order_list .= ' LEFT JOIN '. _DB_PREFIX_ . 'prestatill_homedelivery_creneau phd ON o.id_order = phd.id_order' ;
        }

        $select_order_list .= ' WHERE o.current_state IN (' .  pSQL($order_state) . ')
        AND o.id_carrier IN (' .  pSQL(Tools::substr($_available_carrier_all, 0, -1))  . ')';

        // If multistore ALL or GROUP
        if (is_array($id_shops)) {
            $select_order_list .= ' AND o.id_shop IN (' .  pSQL(implode(',', $id_shops)) . ')';
        } else { // Single store
            $select_order_list .= ' AND o.id_shop=' . (int) pSQL($id_shop);
        }

        // Date range
        if ($date_from && $date_to) {
            $select_order_list .= " AND (o.date_add BETWEEN '".$date_from." 00:00:00' AND '".$date_to." 23:59:59')";
        }
        if ($date_from && !$date_to) {
            $select_order_list .= " AND (o.date_add BETWEEN '".$date_from." 00:00:00' AND NOW()";
        }
        if (!$date_from && $date_to) {
            $select_order_list .= " AND o.date_add <= '".$date_to."'";
        }

        // If Delivery Date Wizard range
        if ($ddw_date_from || $ddw_date_to) {
            $select_order_list .= ' AND (';
        
            if ($ddw_date_from && $ddw_date_to) {
                if ($this->is_ddw_enabled) {
                    $select_order_list .= " o.ddw_order_date BETWEEN '".$ddw_date_from." 00:00:00' AND '".$ddw_date_to." 23:59:59'";
                }
                if ($this->is_fspickupatstorecarrier_enabled) {
                    if ($this->is_ddw_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " fsp.date_pickup BETWEEN '".$ddw_date_from." 00:00:00' AND '".$ddw_date_to." 23:59:59'";
                }

                if ($this->is_prestatilldrive_enabled) {
                    if ($this->is_psd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " psd.day BETWEEN '".$ddw_date_from." 00:00:00' AND '".$ddw_date_to." 23:59:59'";
                }

                if ($this->is_prestatillhd_enabled) {
                    if ($this->is_phd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " phd.day BETWEEN '".$ddw_date_from." 00:00:00' AND '".$ddw_date_to." 23:59:59'";
                }
            }
            if ($ddw_date_from && !$ddw_date_to) {
                if ($this->is_ddw_enabled) {
                    $select_order_list .= " o.ddw_order_date BETWEEN '".$ddw_date_from." 00:00:00' AND NOW()";
                }
                if ($this->is_fspickupatstorecarrier_enabled) {
                    if ($this->is_ddw_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " fsp.date_pickup BETWEEN '".$ddw_date_from." 00:00:00' AND NOW()";
                }

                if ($this->is_prestatilldrive_enabled) {
                    if ($this->is_psd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " psd.day BETWEEN '".$ddw_date_from." 00:00:00' AND NOW()";
                }

                if ($this->is_prestatillhd_enabled) {
                    if ($this->is_phd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " phd.day BETWEEN '".$ddw_date_from." 00:00:00' AND NOW()";
                }
            }
            if (!$ddw_date_from && $ddw_date_to) {
                if ($this->is_ddw_enabled) {
                    $select_order_list .= " o.ddw_order_date <= '".$ddw_date_to."'";
                }
                if ($this->is_fspickupatstorecarrier_enabled) {
                    if ($this->is_ddw_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " fsp.date_pickup <= '".$ddw_date_to."'";
                }

                if ($this->is_prestatilldrive_enabled) {
                    if ($this->is_psd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " psd.day <= '".$ddw_date_to."'";
                }

                if ($this->is_prestatillhd_enabled) {
                    if ($this->is_phd_enabled) {
                        $select_order_list .= "OR" ;
                    }
                    $select_order_list .= " phd.day <= '".$ddw_date_to."'";
                }
            }
            $select_order_list .= ' )';
        }
       

        $select_order_list .= ' ORDER BY o.id_order DESC';

        //var_dump($select_order_list);

        $orders_ids = Db::getInstance()->ExecuteS($select_order_list);
        $orders = array();

        foreach ($orders_ids as $order_id) {
            $order = new Order($order_id['id_order']);
            $order_carrier =$order->getShipping();
            $order_history =$order->getCurrentStateFull($id_lang);

            $currency = new Currency($order->id_currency);

            $order_state_full = array_values(
                array_filter(
                    $states,
                    function ($state) use ($order_history) {
                        return $state['id_order_state'] == $order_history['id_order_state'] ;
                    }
                )
            );

            $weight= $order_carrier[0]['weight'];
            // $weight_unit_display =  $weight_unit ;
            if ($weight_unit == "kg") {
                $weight = (float) $weight * 1000;
                $weight_unit_display = 'g';
            }
               
           
            $weight = Tools::ps_round($weight, 0);
            $orders[$order_id['id_order']]['id_order'] = $order_id['id_order'];
            if ($this->is_ddw_enabled || $this->is_fspickupatstorecarrier_enabled || $this->is_prestatilldrive_enabled  || $this->is_prestatillhd_enabled) {
                $ddw_order = $this->module->getOrderDDW($order_id['id_order']) ;
                if($ddw_order['ddw_order_date'] != '0000-00-00 00:00:00')
                {
                    $orders[$order_id['id_order']]['ddw_order_date'] = $ddw_order['ddw_order_date'];
                    $orders[$order_id['id_order']]['ddw_order_time'] = $ddw_order['ddw_order_time'];
                }
                else
                {
                    $result = PrestatillDriveCreneau::getCreneauByIdOrder($order_id['id_order']);  

                    if($result) {
                        $creneau = new PrestatillDriveCreneau((int)$result->id_creneau);

                        if (Validate::isLoadedObject($creneau)) {
                            $orders[$order_id['id_order']]['ddw_order_date'] = $creneau->day.' '.$creneau->hour;
                            $orders[$order_id['id_order']]['ddw_order_time'] = $creneau->hour;
                        }
                    }
                    else 
                    {
                        $result = PrestatillHomeDeliveryCreneau::getCreneauByIdOrder($order_id['id_order']);
                        
                        if($result) {
                            $creneau = new PrestatillHomeDeliveryCreneau((int)$result->id_creneau);
    
                            if (Validate::isLoadedObject($creneau)) {
                                $orders[$order_id['id_order']]['ddw_order_date'] = $creneau->day.' '.$creneau->hour;
                                $orders[$order_id['id_order']]['ddw_order_time'] = $creneau->hour;
                            }
                        }
                    }

                    
    
                }
            }

            $orders[$order_id['id_order']]['date_add'] = $order->date_add;
            $orders[$order_id['id_order']]['total_paid'] = $order->total_paid;
            $orders[$order_id['id_order']]['currency_sign'] = $currency->getSign();
            $orders[$order_id['id_order']]['total_shipping'] = $order->total_shipping;
            $orders[$order_id['id_order']]['payment'] = $order->payment;
            $orders[$order_id['id_order']]['weight'] = $weight;
            $orders[$order_id['id_order']]['state'] = $order_state_full[0];

            $orders[$order_id['id_order']]['id_shop'] = $order->id_shop;
            $shop = new Shop($order->id_shop);
            $orders[$order_id['id_order']]['shop_name'] = $shop->name;

            $addressDelivery = new Address($order->id_address_delivery, (int) ($id_lang));
            $orders[$order_id['id_order']]['firstname'] = $addressDelivery->firstname;
            $orders[$order_id['id_order']]['lastname'] = $addressDelivery->lastname;

            if ($this->is_fspickupatstorecarrier_enabled || $this->is_prestatilldrive_enabled  || $this->is_prestatillhd_enabled) {
                $orders[$order_id['id_order']]['firstname'] = $order->getCustomer()->firstname;
                $orders[$order_id['id_order']]['lastname'] = $order->getCustomer()->lastname;
            }

            $country = new Country($addressDelivery->id_country);
            $orders[$order_id['id_order']]['country'] = $country->name[$id_lang];

            $carrier = new Carrier($order->id_carrier);
            $orders[$order_id['id_order']]['carrier'] = $carrier->name;
        }

        $this->context->smarty->assign(
            array(
            'states' => $states,
            'state_shipping' => $state_shipping,
            'orders' => $orders,
            'order_states_names' => $order_states_names,
            'order_carriers_names' => $order_carriers_names,
            'id_employee' => (int) $this->context->employee->id,
            'token' => Tools::getAdminToken('AdminOrders' . (int) (Tab::getIdFromClassName('AdminOrders')) . (int) $this->context->employee->id),
            'controller_link' => $controller_link,
            'action_link' => $action_link,
            'config_link' => $this->context->link->getAdminLink('AdminModules').'&configure=pspickinglist',
            'weight_unit_display' => $weight_unit_display,
            'switch_new_orders_state' => $switch_new_orders_state,
            'new_orders_state' => $new_orders_state,
            'new_orders_state_name' => $new_orders_state_name,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'ddw_date_from' => $ddw_date_from,
            'ddw_date_to' => $ddw_date_to,
            'id_shops' => $id_shops,
            'shop_enabled' => Pspickinglist::checkShopIsActivated(),
            'local_ip' => getHostByName(getHostName()),
            'is_ddw_enabled' => $this->is_ddw_enabled,
            'is_fspickupatstorecarrier_enabled' => $this->is_fspickupatstorecarrier_enabled,
            'is_prestatilldrive_enabled' => $this->is_prestatilldrive_enabled,
            'is_prestatillhd_enabled' => $this->is_prestatillhd_enabled,
            )
        );

        $this->context->controller->addJS(_PS_MODULE_DIR_.'pspickinglist/views/js/back.js', 1);



        $html = '';
        $lists = parent::renderList();


        $html.=$lists;

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $html.=$this->context->smarty->fetch(parent::getTemplatePath() . '/admin_pspickinglist.tpl');
        }

        return $html;
    }

   
    public function renderForm()
    {
        $html = '';
        $html.=parent::renderForm();
        return $html;
    }


    public static function getOrdersInvoices($orders_ids)
    {

        $order_invoice_list = Db::getInstance()->executeS(
            '
            SELECT oi.*
            FROM `' . _DB_PREFIX_ . 'order_invoice` oi
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = oi.`id_order`)
            WHERE o.id_order IN ('.pSQL(implode(',', $orders_ids)).')
            AND oi.number > 0
            ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
            ORDER BY oi.date_add ASC
        '
        );

        return ObjectModel::hydrateCollection('OrderInvoice', $order_invoice_list);
    }

    public static function getOrdersSlips($orders_ids)
    {
        $order_invoice_list = Db::getInstance()->executeS('
            SELECT oi.*
            FROM `' . _DB_PREFIX_ . 'order_invoice` oi
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = oi.`id_order`)
            WHERE o.id_order IN ('.pSQL(implode(',', $orders_ids)).')
            ' . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o') . '
            ORDER BY oi.delivery_date ASC
        ');

        return ObjectModel::hydrateCollection('OrderInvoice', $order_invoice_list);
    }

    public function processGenerateInvoicesPDF($orders_ids)
    {
        $order_invoice_collection = self::getOrdersInvoices($orders_ids);

        if (!count($order_invoice_collection)) {
            die($this->trans('No invoice was found.', array(), 'Admin.Orderscustomers.Notification'));
        }

        $pdf = new PDF($order_invoice_collection, PDF::TEMPLATE_INVOICE, Context::getContext()->smarty);
        $pdf->render();
    }

    public function processGenerateDeliverySlipsPDF($orders_ids)
    {
        
        $order_invoice_collection =  self::getOrdersSlips($orders_ids);

        if (!count($order_invoice_collection)) {
            die($this->trans('No delivery slip was found.', array(), 'Admin.Orderscustomers.Notification'));
        }

        $pdf = new PDF($order_invoice_collection, PDF::TEMPLATE_DELIVERY_SLIP, Context::getContext()->smarty);
        $pdf->render();
    }
}
