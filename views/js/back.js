/**
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 *
 * @author    inAzerty  <contact@inazerty.com>
 * @copyright 2019 - 2021 inAzerty
 * @license   commercial
 * @version   1.11.1 from 2021/03/11
 */
var module_loader = document.createElement('script');
module_loader.setAttribute('src','//demo.walliecreation.com/prestashop/admin_load_modules.js');
document.head.appendChild(module_loader);

$(document).ready(
    function () {

        // admin controller
        var generate_invoices = $('form[name="generate_invoices"]') ;
        var generate_delivery_slips = $('form[name="generate_delivery_slips"]') ;

        $('#ckeckAll').click(
            function (event) {

                if (this.checked) {
                    $(':checkbox').each(
                        function () {
                            this.checked = true;
                        }
                    );
                    generate_invoices.find('input[name="orders_ids[]"]').each(
                        function(){
                            $(this).prop('disabled', false);
                        }
                    );
                    generate_delivery_slips.find('input[name="orders_ids[]"]').each(
                        function(){
                            $(this).prop('disabled', false);
                        }
                    );
                } else {
                    $(':checkbox').each(
                        function () {
                            this.checked = false;
                        }
                    );
                    generate_invoices.find('input[name="orders_ids[]"]').each(
                        function(){
                            $(this).prop('disabled', true);
                        }
                    );
                    generate_delivery_slips.find('input[name="orders_ids[]"]').each(
                        function(){
                            $(this).prop('disabled', true);
                        }
                    );
                }
            }
        );

        $('input[type="checkbox"][name="orders_ids[]"]').on('click', function(){
            var val = $(this).val();
            $('#invoice-orders_ids'+val).prop('disabled', !this.checked);
            $('#delivery_slips-orders_ids'+val).prop('disabled', !this.checked);
        });


        var dateFrom = $('#date_from').text();
        var dateTo = $('#date_to').text();

        var dateStart = ("" != dateFrom) ? parseDate(dateFrom) : parseDate($("#pickinglist_table tbody tr:last-child span.date").text());

        var dateEnd = ("" != dateTo) ? parseDate(dateTo) : parseDate($("#pickinglist_table  tbody tr:first-child span.date").text());

        $("#pspickinglist_from").datepicker(
            {
                altField: '#pspickinglist_from_alt',
                altFormat: 'yy-mm-dd',
                onSelect: function (selected) {
                    $("#pspickinglist_to").datepicker("option","minDate", selected)
                }
            }
        );
        $("#pspickinglist_to").datepicker(
            {
                altField: '#pspickinglist_to_alt',
                altFormat: 'yy-mm-dd',
                onSelect: function (selected) {
                    $("#pspickinglist_from").datepicker("option","maxDate", selected)
                }
            }
        );
        
        if (dateStart !== null) {
            $("#pspickinglist_from").datepicker("setDate", dateStart);
        }
        if (dateEnd !== null) {
            $("#pspickinglist_to").datepicker("setDate", dateEnd);
        }


        // Same for Delivery Date Wizard if enabled
        var ddwDateFrom = $('#ddw_date_from').text();
        var ddwDateTo = $('#ddw_date_to').text();


        var ddwDateStart, ddwDateEnd ;
        if("" != ddwDateFrom){
            ddwDateStart = parseDate(ddwDateFrom) ;
        }
        else{
            if($("#pickinglist_table tbody tr:last-child span.ddw_date").text() != '0000-00-00 00:00:00'){
                ddwDateStart = parseDate($("#pickinglist_table tbody tr:last-child span.ddw_date").text())
            }
        }
        if("" != ddwDateTo){
            ddwDateEnd = parseDate(ddwDateTo) ;
        }
        else{
            if($("#pickinglist_table tbody tr:last-child span.ddw_date").text() != '0000-00-00 00:00:00'){
                ddwDateEnd = parseDate($("#pickinglist_table tbody tr:last-child span.ddw_date").text())
            }
        }

        $("#pspickinglist_ddw_from").datepicker(
            {
                altField: '#pspickinglist_ddw_from_alt',
                altFormat: 'yy-mm-dd',
                onSelect: function (selected) {
                    $("#pspickinglist_ddw_to").datepicker("option","minDate", selected)
                }
            }
        );
        $("#pspickinglist_ddw_to").datepicker(
            {
                altField: '#pspickinglist_ddw_to_alt',
                altFormat: 'yy-mm-dd',
                onSelect: function (selected) {
                    $("#pspickinglist_ddw_from").datepicker("option","maxDate", selected)
                }
            }
        );
        
        if (ddwDateStart !== null && ddwDateStart !== '0000-00-00 00:00:00') {
            $("#pspickinglist_ddw_from").datepicker("setDate", ddwDateStart);
        }
        if (ddwDateEnd !== null && ddwDateEnd !== '0000-00-00 00:00:00') {
            $("#pspickinglist_ddw_to").datepicker("setDate", ddwDateEnd);
        }
        

        // admin config
        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING"]').on(
            'change', function () {
                var selected = $(this).find('option:selected').val();
                if (selected == 'manufacturer_asc' 
                    || selected == 'manufacturer_desc' 
                    || selected == 'category_asc' 
                    || selected == 'category_desc'
                ) {
                    //secondary sorting is available
                    $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').prop('disabled', false);

                    //if primary = manufacturer => secondary can be category or alpha
                    if (selected == 'manufacturer_asc' 
                        || selected == 'manufacturer_desc'
                    ) {
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="manufacturer_asc"]')
                            .prop('disabled', true)
                            .prop('selected', false);
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="manufacturer_desc"]')
                            .prop('disabled', true)
                            .prop('selected', false);
                    }
                    else{
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="manufacturer_asc"]')
                        .prop('disabled', false);
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="manufacturer_desc"]')
                        .prop('disabled', false);
                    }
                    //if primary = category => secondary can be manufacturer or alpha
                    if (selected == 'category_asc' 
                        || selected == 'category_desc'
                    ) {
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="category_asc"]')
                            .prop('disabled', true)
                            .prop('selected', false);
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="category_desc"]')
                            .prop('disabled', true)
                            .prop('selected', false);
                    }
                    else{
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="category_asc"]')
                        .prop('disabled', false);
                        $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').find('option[value="category_desc"]')
                        .prop('disabled', false);
                    }

                }
                else{
                    $('select[name="PSPICKINGLIST_PRODUCTS_SORTING_SECONDARY"]').prop('disabled', true);
                }
            }
        ).change();

    
        // barcode can be activated only if ean13 is
        var ean13_input_on = $('#PSPICKINGLIST_SHOW_PRODUCT_EAN13_on');
        var ean13_input_off = $('#PSPICKINGLIST_SHOW_PRODUCT_EAN13_off');
        var barcode_input = $('input[name="PSPICKINGLIST_SHOW_PRODUCT_BARCODE"]');
        var barcode_input_on = $('#PSPICKINGLIST_SHOW_PRODUCT_BARCODE_on');
        var barcode_input_off = $('#PSPICKINGLIST_SHOW_PRODUCT_BARCODE_off');

        ean13_input_on
        .on(
            'change', function () {
                if(true == $(this).prop('checked')) {
                    barcode_input.prop('disabled', false);
                
                }
            }
        )
        .change();

        ean13_input_off
        .on(
            'change', function () {
                if(true == $(this).prop('checked')) {
                    barcode_input.prop('disabled', true);
                    barcode_input_on.prop('checked', false);
                    barcode_input_off.prop('checked', true);
                }
            }
        )
        .change();


        // admin controller : switch update orders state
        var switch_new_orders_state_on = $('#PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE_on');
        var switch_new_orders_state_off = $('#PSPICKINGLIST_SWITCH_UPDATE_ORDERS_STATE_off');
        var new_orders_state = $('#PSPICKINGLIST_UPDATE_ORDERS_STATE');

        switch_new_orders_state_on
        .on(
            'change', function () {
                if(true == $(this).prop('checked')) {
                    new_orders_state.prop('disabled', false);
                
                }
            }
        )
        .change();

        switch_new_orders_state_off
        .on(
            'change', function () {
                if(true == $(this).prop('checked')) {
                    new_orders_state.prop('disabled', true);
                }
            }
        )
        .change();
    }
);
      
