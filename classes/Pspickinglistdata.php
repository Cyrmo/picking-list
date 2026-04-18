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

class Pspickinglistdata
{
    private $data ;
    private $products ;

    public function __construct($orders_ids)
    {
        $details = [];
        $data = [];
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (is_array($orders_ids)) {
            foreach ($orders_ids as $order_id) {
                $order = new Order($order_id);
                $details[] = $order->getOrderDetailList();
            }
        }


        foreach ($details as $item) {
            foreach ($item as $k => $product) {
                if ('explode' == Configuration::get('PSPICKINGLIST_PACK_BEHAVIOUR') && Pack::isPack($product['product_id'])) { // check if is pack
                    $items = Pack::getItems($product['product_id'], $id_lang);
                    $_product = $product ;
                    $product_quantity = $product['product_quantity'] ;
                    unset($item[$k]);
                    foreach ($items as $pack_product) { // replace actual pack by these items
                        // delete original product (pack)
                        // $_product['product_quantity'] = (int) $product['product_quantity'] ;
                        $_product['product_id'] = $pack_product->id ;
                        $_product['product_name'] = Product::getProductName($pack_product->id, $pack_product->id_pack_product_attribute, $id_lang);
                        $_product['product_attribute_id'] = $pack_product->id_pack_product_attribute ;
                        $_product['product_quantity'] = $product_quantity * $pack_product->pack_quantity ;
                       
                        // $product['id_customization'] = $product['product_quantity'] * $pack_product->pack_quantity ;
                        $_product['link_rewrite'] = $pack_product->link_rewrite ;
                        $data[] = $_product ;
                    }
                } else { // standard product
                    $data[] = $product ;
                }
            }
        }

       

        // products list creation
        // products line are grouped by variations
        // total_quantity is the sum of product_quantity of each variation of a same product_id
        $this->products = [];


        //main products array, fetching image id
        foreach ($data as $element) {
            $order = new Order($element['id_order']);

           

            $id_shop = $element['order_id_shop'] = $order->id_shop ; // @since 1.6

            // Get product cover
            $cover = Product::getCover((int)$element['product_id']);
            $id_cover = $cover['id_image'] ;
            // Instanciate new Image from cover id
            $image = new Image($id_cover);

            // new way to get images
            $image_name = 'product_mini_' . (int) $element['product_id'] . (isset($element['product_attribute_id']) ? '_' . (int) $element['product_attribute_id'] : '') . '.jpg';
            $path = _PS_PROD_IMG_DIR_ . $image->getExistingImgPath() . '.jpg';

            $thumbnail = ImageManager::thumbnail($path, $image_name, 45, 'jpg', false) ;
            $element['image_tag'] = preg_replace(
                '/\.*' . preg_quote(__PS_BASE_URI__, '/') . '/',
                _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR,
                $thumbnail,
                1
            );

            /**
             * @since 1.11.1
             * In some rare cases, preg_replace above returns null
             * In this case, we keep the raw geenrated thumbnail code
             */
            if (null == $element['image_tag'] || !$element['image_tag']) {
                $element['image_tag'] = $thumbnail ;
            }

            if (file_exists(_PS_TMP_IMG_DIR_ . $image_name)) {
                $element['image_size'] = getimagesize(_PS_TMP_IMG_DIR_ . $image_name);
            } else {
                $element['image_size'] = false;
            }

            $product = new Product($element['product_id'], true, $id_lang, $id_shop);

            /**
             * @since 1.5.0
             * Pick product name according to product table in default language rather than in order_details table (where names are in customer language)
             * We keep default order_details product name by default if main name is null
             */
            $order_product_name = $element['product_name'] ;
            $element['product_name'] =  Product::getProductName($element['product_id'], $element['product_attribute_id'], $id_lang);
            if (null == $element['product_name']) {
                 $element['product_name'] = $order_product_name ;
            }

            /**
             * @since 1.11.1
             */
            $order_ref_mode = Configuration::get('PSPICKINGLIST_ORDER_REF_MODE') ;
            $addressDelivery = new Address($order->id_address_delivery, (int) ($id_lang));
            switch ($order_ref_mode) {
                case 'order_ref':
                    $element['order_reference'] = $order->reference.' ('.$element['product_quantity'].')' ;
                    break;

                case 'order_customer':
                    $element['order_reference'] = $addressDelivery->firstname . ' '.Tools::strtoupper($addressDelivery->lastname).' ('.$element['product_quantity'].')';
                    break;

                case 'order_id_customer':
                    $element['order_reference'] = '#'.$order->id.' '.$this->l('by', true).' '.$addressDelivery->firstname . ' '.Tools::strtoupper($addressDelivery->lastname).' ('.$element['product_quantity'].')';
                    break;

                case 'order_ref_customer':
                    $element['order_reference'] = $order->reference.' '.$this->l('by', true).' '.$addressDelivery->firstname . ' '.Tools::strtoupper($addressDelivery->lastname).' ('.$element['product_quantity'].')';
                    break;
                
                default: // default = Order ID
                    $element['order_reference'] = '#'.$order->id.' ('.$element['product_quantity'].')' ;
                    break;
            }
           
           
          

            $order_references_separator = ' - ';

            if (version_compare(_PS_VERSION_, '1.7', '<')) { // no value id_customization in 1.6
                $element['id_customization'] = null ;
            }

            //ref + ean13 single products
            $product_reference = $product->reference ;
            $product_ean13 = '' ;
            $product_ean13 = $product->ean13 ;
            // $product_barcode = '';


            //same for variable
            if ((int) $element['product_attribute_id'] > 0) {
                $product_attribute_reference = Db::getInstance()->getValue('SELECT reference FROM '._DB_PREFIX_.'product_attribute WHERE id_product_attribute = '.(int)$element['product_attribute_id']);
                if ($product_attribute_reference && null != $product_attribute_reference) {
                    $product_reference = $product_attribute_reference ;
                }

                $product_attribute_ean13 = Db::getInstance()->getValue('SELECT ean13 FROM '._DB_PREFIX_.'product_attribute WHERE id_product_attribute = '.(int)$element['product_attribute_id']);
                if ($product_attribute_ean13 && null != $product_attribute_ean13) {
                    $product_ean13 = $product_attribute_ean13 ;
                }
            }

            // product supplier reference
            // since 1.2.1 from 2019/01/06 this data is picked directly in product_supplier_reference table instead of order_details table

            $product_suppliers_references = Db::getInstance()->ExecuteS('SELECT ps.product_supplier_reference, s.name FROM '._DB_PREFIX_.'product_supplier AS ps LEFT JOIN '._DB_PREFIX_.'supplier s ON ps.id_supplier = s.id_supplier WHERE ps.id_product = '.(int)$element['product_id'].' AND ps.id_product_attribute = '.(int)$element['product_attribute_id']);


            if (!isset($this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']])) {
                $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']] = $element;
            } else {
                $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['id_order'] .= ','.$element['id_order'];

                $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['order_reference'] .= $order_references_separator.$element['order_reference'];

                $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['product_quantity'] += $element['product_quantity'];
            }


            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['image'] = $image;
            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['image_tag'] = $element['image_tag'];

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['link_rewrite'] = $product->link_rewrite;

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['manufacturer_name'] = $product->manufacturer_name;

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['category_name'] = $product->category;

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['reference'] = $product_reference;

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['ean13'] = $product_ean13;

            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['suppliers_references'] = $product_suppliers_references;


            // get quantity
            $quantity = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
                SELECT `quantity` 
                FROM `' . _DB_PREFIX_ . 'stock_available`
                WHERE `id_product` = ' .  pSQL($element['product_id']) . '
                AND `id_product_attribute` = ' . (int) pSQL($element['product_attribute_id']).'
                AND `id_shop` = ' . (int) pSQL($id_shop)
            );

            // get physical_quantity
             // test if column physical_quantity exists in stock_available table (column seems to not exist before  1.6.1.23)
            $physical_quantity = "";
            $result0 =  Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'stock_available` LIKE \'physical_quantity\'');
            $physical_quantity_col_exists = count($result0) > 0 ? true : false ;
            if ($physical_quantity_col_exists) {
                $physical_quantity = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    '
                    SELECT `physical_quantity`
                    FROM `' . _DB_PREFIX_ . 'stock_available`
                    WHERE `id_product` = ' .  pSQL($element['product_id']) . '
                    AND `id_product_attribute` = ' . (int) pSQL($element['product_attribute_id']).'
                    AND `id_shop` = ' . (int) pSQL($id_shop)
                );
            }

            // get location in warehouse
            if (version_compare(_PS_VERSION_, '1.7', '<')) { // PS 1.6
                $location = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    '
                SELECT `location` 
                FROM `' . _DB_PREFIX_ . 'warehouse_product_location`
                WHERE `id_product` = ' .  pSQL($element['product_id']) . '
                AND `id_product_attribute` = ' . (int) pSQL($element['product_attribute_id']).'
                AND `id_warehouse` = ' . (int) pSQL($element['id_warehouse'])
                );
            } else { // PS 1.7
                // test if column location exists in stock_available table (1.7.5.x)
                $result =  Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'stock_available` LIKE \'location\'');
                $location_col_exists = count($result) > 0 ? true : false ;

                if ($location_col_exists) {
                    $location = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                        '
                    SELECT `location` 
                    FROM `' . _DB_PREFIX_ . 'stock_available`
                    WHERE `id_product` = ' .  pSQL($element['product_id']) . '
                    AND `id_product_attribute` = ' . (int) pSQL($element['product_attribute_id']).'
                    AND `id_shop` = ' . (int) pSQL($id_shop)
                    );
                } else { //1.7.1 to 1.7.4 ?
                    $location = null ;
                }
            }


            // $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['stock_quantity'] = $quantity;

            // quantity
            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['stock_quantity'] = $quantity ;

            // physical quantity
            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['stock_physical_quantity'] = $physical_quantity ;

            // location
            $this->products[$element['product_id'].$element['product_attribute_id'].$element['id_customization']]['location'] = $location;
        }


        /**
         * @since 1.5.1
         * order_references become an array in order to explode
         * and display it with clean break line in PDF
         */
        if (false === stripos(Configuration::get('PSPICKINGLIST_MODE'), 'CSV')) {
            $this->products = array_map(
                function ($el) {
                    $el['order_reference'] = explode(' - ', $el['order_reference']);
                    return $el ;
                },
                $this->products
            );
        }

        // echo '<pre>';
        //     print_r($this->products);
        //     echo '</pre>';
        //     die();

        // sum : getting total_quantity
        $tmp = [];
        foreach ($this->products as $v) {
            $id = $v['product_id'];
            $tmp[$id][] = $v['product_quantity'];
        }
    

        foreach ($this->products as $key => $value) {
            if (array_key_exists($this->products[$key]['product_id'], $tmp)) {
                $this->products[$key]['total_quantity'] =  array_sum($tmp[$this->products[$key]['product_id']]);
            }
        }

        //final output
        $tmp2 = [];

        //values used for sorting
        foreach ($this->products as $key => $value) {
            $tmp2[$value['product_id']]['products'][] = $value ;
            $tmp2[$value['product_id']]['total_quantity'] = $value['total_quantity'] ;
            $tmp2[$value['product_id']]['product_name'] = $value['product_name'] ;
            $tmp2[$value['product_id']]['manufacturer_name'] = $value['manufacturer_name'] ;
            $tmp2[$value['product_id']]['category_name'] = $value['category_name'] ;
            $tmp2[$value['product_id']]['location'] = $value['location'] ;
        }

        $this->products = $tmp2 ;


        //sorting depending on configuration
        $sorting = Configuration::get('PSPICKINGLIST_PRODUCTS_SORTING');
        $sorting_secondary = Configuration::get('PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY');


        switch ($sorting) {
            case 'count_asc':
                usort(
                    $this->products,
                    function ($a, $b) {
                            return $a['total_quantity'] - $b['product_quantity'] ;
                    }
                );
                break;

            case 'alpha_asc':
                usort(
                    $this->products,
                    function ($a, $b) {
                            return strcasecmp($a["product_name"], $b["product_name"]);
                    }
                );
                break;

            case 'alpha_desc':
                $products = $this->products ;
                usort(
                    $products,
                    function ($a, $b) {
                            return strcasecmp($a["product_name"], $b["product_name"]);
                    }
                );
                $this->products = array_reverse($products);
                break;
            
            case 'manufacturer_asc':
                usort(
                    $this->products,
                    function ($a, $b) use ($sorting_secondary) {
                        if ($a['manufacturer_name'] == $b['manufacturer_name']) {
                            if ($sorting_secondary == 'category_asc') {
                                return strcasecmp($a["category_name"], $b["category_name"]);
                            } else {
                                return strcasecmp($a["product_name"], $b["product_name"]);
                            }
                        }
                            return strcasecmp($a["manufacturer_name"], $b["manufacturer_name"]);
                    }
                );

                
                break;

            case 'manufacturer_desc':
                $products = $this->products ;
                usort(
                    $products,
                    function ($a, $b) use ($sorting_secondary) {
                        if ($a['manufacturer_name'] == $b['manufacturer_name']) {
                            if ($sorting_secondary == 'category_asc') {
                                return strcasecmp($b["category_name"], $a["category_name"]);
                            } else {
                                return strcasecmp($b["product_name"], $a["product_name"]);
                            }
                        }
                            return strcasecmp($a["manufacturer_name"], $b["manufacturer_name"]);
                    }
                );
                $this->products = array_reverse($products);
                break;

            case 'category_asc':
                usort(
                    $this->products,
                    function ($a, $b) use ($sorting_secondary) {
                        if ($a['category_name'] == $b['category_name']) {
                            if ($sorting_secondary == 'manufacturer_asc') {
                                return strcasecmp($a["manufacturer_name"], $b["manufacturer_name"]);
                            } else {
                                return strcasecmp($a["product_name"], $b["product_name"]);
                            }
                        }
                            return strcasecmp($a["category_name"], $b["category_name"]);
                    }
                );
                break;

            case 'category_desc':
                $products = $this->products ;
                usort(
                    $products,
                    function ($a, $b) use ($sorting_secondary) {
                        if ($a['category_name'] == $b['category_name']) {
                            if ($sorting_secondary == 'manufacturer_asc') {
                                return strcasecmp($b["manufacturer_name"], $a["manufacturer_name"]);
                            } else {
                                return strcasecmp($b["product_name"], $a["product_name"]);
                            }
                        }
                            return strcasecmp($a["category_name"], $b["category_name"]);
                    }
                );
                $this->products = array_reverse($products);
                break;

            case 'location_asc':
                usort(
                    $this->products,
                    function ($a, $b) {
                            return strcasecmp($a["location"], $b["location"]);
                    }
                );
                break;

            case 'location_desc':
                $products = $this->products ;
                usort(
                    $products,
                    function ($a, $b) {
                            return strcasecmp($a["location"], $b["location"]);
                    }
                );
                $this->products = array_reverse($products);
                break;
            
            default: //count_desc
                usort(
                    $this->products,
                    function ($a, $b) {
                            return $b['total_quantity'] - $a['total_quantity'] ;
                    }
                );

                break;
        }

        $this->data = [
            'products' => $this->products ,
            //'employee' => $employee ,
            'orders_count' =>  (int) is_array($orders_ids) ? count($orders_ids) : 0
        ];
    }


    public function getData()
    {
        return $this->data ;
    }
    public function getProducts()
    {
        return $this->products ;
    }

    public function l($string)
    {
        $pspickinglist = Module::getInstanceByName('pspickinglist');
        return $pspickinglist->l($string);
    }
}
