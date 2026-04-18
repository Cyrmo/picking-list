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

class HTMLTemplatePspickinglist extends HTMLTemplate
{
    public $pdf_data;
    public $products;
    public $employee;
    public $orders_count;

    /**
     * @param  $smarty
     * @throws PrestaShopException
     */
    public function __construct($pdf_data, $smarty)
    {
        $this->pdf_data = $pdf_data;
        $this->products = $this->pdf_data['products'];
        $this->employee = $this->pdf_data['employee'];
        $this->orders_count = $this->pdf_data['orders_count'];

        $this->smarty = $smarty;

        // header informations
        $this->title = 'Picking list';

        $this->context = Context::getContext();
        $this->shop = new Shop((int)$this->context->shop->id);
    }

    /**
     * Returns the template's HTML content
     *
     * @return string HTML content
     */
    public function getContent()
    {
        $this->smarty->assign(
            array(
                'products' => $this->products,
                'products_count' => array_reduce(
                    $this->products,
                    function ($carry, $item) {
                        $carry += count($item['products']);
                        return $carry;
                    }
                ),
                'products_sum' => array_reduce(
                    $this->products,
                    function ($carry, $item) {
                        $carry += $item['total_quantity'];
                        return $carry;
                    }
                ),
                'shop_address' => AddressFormat::generateAddress($this->shop->getAddress(), array(), '<br />', ' '),
                'employee' => $this->employee,
                'orders_count' => $this->orders_count
            )
        );

        $tpls = array(
            'style_tab' => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'products_tab' => $this->smarty->fetch(_PS_MODULE_DIR_ . 'pspickinglist/views/templates/front/products-list.tpl'),
        );
        $this->smarty->assign($tpls);
       

        //return $this->smarty->fetch($this->getTemplate('pspickinglist'));
        return $this->smarty->fetch(_PS_MODULE_DIR_ . 'pspickinglist/views/templates/front/pspickinglist.tpl');
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     */
    public function getFilename()
    {
        return 'pspickinglist.pdf';
    }

    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'pspickinglist.pdf';
    }

    public function getHeader()
    {
        $this->assignCommonHeaderData();

        $this->smarty->assign(
            array(
            'header' => 'Picking list',
            'date' => date('d-m-Y'),
            'title' => $this->l('Edited by'). ' ' .$this->employee->lastname. ' ' . $this->employee->firstname,
            )
        );
        return $this->smarty->fetch($this->getTemplate('header'));
    }
    public function getFooter()
    {
        return "" ;
    }
}
