<?php
/**
 * 2019 - 2020 inAzerty
 * module Pspickinglist
 *
 * @author    inAzerty  <contact@inazerty.com>
 * @copyright 2019 - 2020 inAzerty
 * @license   commercial
 * @version   1.11.1 from 2021/03/11
 */

require_once _PS_MODULE_DIR_ . 'pspickinglist/classes/Pspickinglistdata.php';
require_once _PS_MODULE_DIR_ . 'pspickinglist/classes/HTMLTemplatePspickinglist.php';

class PspickinglistRenderpdfModuleFrontController extends FrontController
{
    protected $display_header = false;
    protected $display_footer = false;
    public $pdf_data = [];

    // public function init(){
    //     var_dump($_POST);
    //     die();
    // }
    // public function initContent(){
    //     var_dump($_POST);
    //     die();
    // }

    public function setPdfData($data)
    {
        $this->pdf_data = $data ;
    }

    public function postProcess()
    {

        $id_employee = Tools::getValue('id_employee');
        $orders_ids = Tools::getValue('orders_ids');
        $shop_context = Tools::getValue('shop_context');

        //var_dump($orders_ids);die();

        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $employee = new Employee($id_employee, $id_lang);

        
        $pickinglistdata = new Pspickinglistdata($orders_ids, $shop_context); // construct PDF data
        $this->pdf_data = $pickinglistdata->getData();
        $this->pdf_data['employee'] = $employee ;
        parent::postProcess();
    }

    public function display()
    {
        $pdf = new PDF([$this->pdf_data], 'Pspickinglist', Context::getContext()->smarty);
        $pdf->render();
    }
}
