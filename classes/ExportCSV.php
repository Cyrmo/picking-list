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

class ExportCSV
{
    const LINE_RETURN = "\r\n";

    public function l($string)
    {
        $pspickinglist = Module::getInstanceByName('pspickinglist');
        return $pspickinglist->l($string);
    }


    public function export($products)
    {
        $csv = $this->getCsvContent($products);

        $fileName = 'pspickinglist.csv';

        $this->outputCsv($fileName, $csv); // remove first line wallie 01/2019
    }

    public function getCsvContent($products)
    {
        $csv = [];
        $header = [];
        


        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_ID')) {
            $header[] = $this->l('Product ID');
        }
        
        $header[] = $this->l('Name');

        $header[] = $this->l('Quantity');

        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_REF')) {
            $header[] = $this->l('Ref.');
        }

        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_EAN13')) {
            $header[] = $this->l('EAN-13/JAN');
        }
        
        if (Configuration::get('PSPICKINGLIST_SHOW_STOCK_QUANTITY')) {
            $header[] = $this->l('Remaining quantity in stock');
        }

        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY')) {
            $header[] = $this->l('Category');
        }

        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER')) {
            $header[] = $this->l('Manufacturer');
        }

      

        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF')) {
            $header[] = $this->l('Supplier ref');
        }
        
        if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_LOCATION')) {
            $header[] = $this->l('Warehouse location');
        }
        if (Configuration::get('PSPICKINGLIST_ORDER_REF_MODE') != 'hide') {
            $header[] = $this->l('In order(s)');
        }

        $csv[]=$header ;

        //  $csv = Tools::substr($csv, 0, -1); // remove last ; to avoid empty column

        //  $csv .= self::LINE_RETURN ; //end of header

        foreach ($products as $products_group) {
            foreach ($products_group['products'] as $product) {
                $line = [];
                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_ID')) {
                    $line[]= $product['product_id'] ;
                }
                $line[]= $product['product_name'] ;
            
                $line[]= $product['product_quantity'] ;
            
                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_REF')) {
                    $line[]= $product['reference'] ;
                }

                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_EAN13')) {
                    $line[]= $product['ean13']  ;
                }

                if (Configuration::get('PSPICKINGLIST_SHOW_STOCK_QUANTITY')) {
                    $line[]= $product['stock_quantity'] ;
                }

                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY')) {
                    $line[]= $product['category_name'] ;
                }
            
                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER')) {
                    $line[]= $product['manufacturer_name'] ;
                }


                // if (is_array($product['order_reference'])) {
                //     $line[]= implode(' - ', $product['order_reference']);
                // }

                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF')) {
                    foreach ($product['suppliers_references'] as $element) {
                        if ($element['product_supplier_reference'] != "") {
                            $line[]= $element['product_supplier_reference'] . ' ('.$element['name'].') ';
                        }
                    }
                }
            
                if (Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_LOCATION')) {
                    $line[]= $product['location'] ;
                }

                if (Configuration::get('PSPICKINGLIST_ORDER_REF_MODE') != 'hide') {
                    $line[]= $product['order_reference'] ;
                }

                $csv[]= $line ;

                //  $csv = Tools::substr($csv, 0, -1); // remove last semi-colon to avoid empty column

                // $csv .= self::LINE_RETURN;
            }
        }

       
        // $csv[]=$lines ;

        // print_r($csv);
        // die();


        return array_map(
            function ($el) {
                $el = str_replace('"', '“', $el);
                $el = str_replace(';', ':', $el);
                return $el ;
            },
            $csv
        );
    }

    protected function outputCsv($fileName, $csv)
    {

        $csv_mode = Configuration::get('PSPICKINGLIST_MODE');
        if (!$csv_mode) {
            $csv_mode = 'CSV' ;
        }
        header('Content-Encoding: UTF-8');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $fp = fopen("php://output", 'w');

        /**
 * @since 1.5.0
         * Try to deal with Ms Excel and utf8
         * https://stackoverflow.com/questions/4348802/how-can-i-output-a-utf-8-csv-in-php-that-excel-will-read-properly
         */
        if ($csv_mode == 'CSV_excel') {
            header('Content-Type: application/vnd.ms-excel');
            //flatten array
            $string = "";
            foreach ($csv as $row) {
                $string.= implode(';', $row).self::LINE_RETURN ;
            }
            $encoded_csv = mb_convert_encoding($string, 'UTF-16LE', 'UTF-8');
            header('Content-Length: '. Tools::strlen($encoded_csv));
            echo chr(255) . chr(254) . $encoded_csv; // UTF8 BOM
            die();
        }

       
        // Standard CSV
        foreach ($csv as $row) {
            fputcsv($fp, $row, ';');
        }
        fclose($fp);
        die();
    }
}
