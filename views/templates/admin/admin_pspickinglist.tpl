{** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}

 {if Configuration::get('PSPICKINGLIST_MODE') && !$shop_enabled}
     <div class="alert alert-danger clearfix" role="alert">
        <p>{include file="./shop_disabled_message.tpl" }</p>
     </div>
 {/if}

<div class="alert alert-info clearfix" role="alert">

    <p>
        {l s='Check orders to include in the picking list' mod='pspickinglist'}
    </p>

    <p>
        <strong>
            {count($order_states_names)|escape:'htmlall':'UTF-8'} {l s='order state(s):' mod='pspickinglist'}
        </strong>
        {foreach from=$order_states_names item=order_state_name name=order_states_names}
            <em>{$order_state_name.name|escape:'htmlall':'UTF-8'}</em>
            {if $smarty.foreach.order_states_names.index + 1  != count($order_states_names) }
            ,
            {/if}
        {/foreach}
        <br>
        <strong>
            {count($order_carriers_names)|escape:'htmlall':'UTF-8'} {l s='carrier(s):' mod='pspickinglist'}
        </strong>

        {foreach from=$order_carriers_names item=order_state_name name=order_carriers_names}
            <em>{$order_state_name.name|escape:'htmlall':'UTF-8'}</em>
            {if $smarty.foreach.order_carriers_names.index + 1  != count($order_carriers_names) }
            ,
            {/if}
        {/foreach}

        <br>
        
        <strong>
            {l s='Update orders state once pickinglist is generated?' mod='pspickinglist'}
        </strong>
        
        {if $switch_new_orders_state && $new_orders_state != "" && $new_orders_state != 'none'}
            {l s='Yes, new state:' mod='pspickinglist'}
            <em>{$new_orders_state_name|escape:'htmlall':'UTF-8'}</em>
        {else}
            {l s='No' mod='pspickinglist'}
        {/if}
  
    </p>
   
</div>

<div class="panel" >

    <div class="panel-heading" style="max-width:100%;overflow-x:auto;text-overflow:initial">
        {l s='Orders' mod='pspickinglist'}
        <span class="badge">{$orders|@count|escape:'htmlall':'UTF-8'}</span>
    </div>


    {if $orders!=""}

        <div class="table-responsive-row clearfix">

            <div class="pull-right clearix" style="display:flex">

             <form style="display:inline-block;margin-right:.25rem"  method="post" action="{$action_link|escape:'htmlall':'UTF-8'}" name="generate_invoices">
             {foreach from=$orders item=order name=orders}
            
                <input id="invoice-orders_ids{$order.id_order|escape:'htmlall':'UTF-8'}" type="hidden" name="orders_ids[]" value="{$order.id_order|escape:'htmlall':'UTF-8'}">
                {/foreach}
                  <button {if count($orders) < 1}disabled{/if} name="generate_invoices" type="submit" class="btn btn-default">
                     <i class="icon-download"></i>
                     {l s='Generate orders invoices' mod='pspickinglist'}
                  </button>
             </form>

             <form style="display:inline-block;margin-right:2rem"  method="post" action="{$action_link|escape:'htmlall':'UTF-8'}" name="generate_delivery_slips">
             {foreach from=$orders item=order name=orders}
            
                <input id="delivery_slips-orders_ids{$order.id_order|escape:'htmlall':'UTF-8'}" type="hidden" name="orders_ids[]" value="{$order.id_order|escape:'htmlall':'UTF-8'}">
                {/foreach}
                  <button {if count($orders) < 1}disabled{/if} name="generate_delivery_slips" type="submit" class="btn btn-default">
                     <i class="icon-download"></i>
                     {l s='Generate orders delivery slips' mod='pspickinglist'}
                  </button>
             </form>



            <div>
                <form class="date_range pull-left clearix" method="post" action="{$controller_link|escape:'htmlall':'UTF-8'}" name="search_date_range">

                    <div style="display:none" id="date_from">{$date_from|escape:'htmlall':'UTF-8'}</div>
                    <div style="display:none" id="date_to">{$date_to|escape:'htmlall':'UTF-8'}</div>

                    <div class="pull-left">
                        <div class="input-group fixed-width-md center">
                            <input type="text" class="filter datepicker date-input form-control" id="pspickinglist_from" name="pspickinglist_from"  placeholder="{l s='From' mod='pspickinglist'}" />
                            <input type="hidden" id="pspickinglist_from_alt" name="pspickinglist_from_alt" value="">
                            <span class="input-group-addon">
                                <i class="icon-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="pull-left">&nbsp;</div>
                    <div class="pull-left">
                        <div class="input-group fixed-width-md center">
                            <input type="text" class="filter datepicker date-input form-control" id="pspickinglist_to" name="pspickinglist_to"  placeholder="{l s='To' mod='pspickinglist'}" />
                            <input type="hidden" id="pspickinglist_to_alt" name="pspickinglist_to_alt" value="">
                            <span class="input-group-addon">
                                <i class="icon-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="pull-left">&nbsp;</div>
                    <div class="pull-left">
                        <button class="btn btn-default" type="submit">
                            <i class="icon-filter"></i>
                            {l s='Filter by date range' mod='pspickinglist'}
                        </button>
                    </div>

                </form>
                
                {if $date_from || $date_to}
                
                    <div class="pull-left">&nbsp;</div>
                    <div class="pull-left">
                        <a href="{$controller_link}" class="btn btn-default">
                            <i class="icon-refresh"></i>
                            {l s='Reset' mod='pspickinglist'}
                        </a>
                    </div>
                {/if}

                {if $is_ddw_enabled || $is_fspickupatstorecarrier_enabled || $is_psd_enabled || $is_prestatilldrive_enabled  || $is_prestatillhd_enabled}
                    
                    <div class="clear clearfix"></div>


                    <form class="date_range pull-left clearix" method="post" action="{$controller_link|escape:'htmlall':'UTF-8'}" name="search_ddw_date_range">

                        <div style="display:none" id="ddw_date_from">{$ddw_date_from|escape:'htmlall':'UTF-8'}</div>
                        <div style="display:none" id="ddw_date_to">{$ddw_date_to|escape:'htmlall':'UTF-8'}</div>

                        <div class="pull-left">
                            <div class="input-group fixed-width-md center">
                                <input type="text" class="filter datepicker date-input form-control" id="pspickinglist_ddw_from" name="pspickinglist_ddw_from"  placeholder="{l s='From' mod='pspickinglist'}" />
                                <input type="hidden" id="pspickinglist_ddw_from_alt" name="pspickinglist_ddw_from_alt" value="">
                                <span class="input-group-addon">
                                    <i class="icon-calendar"></i>
                                </span>
                            </div>
                        </div>
                        <div class="pull-left">&nbsp;</div>
                        <div class="pull-left">
                            <div class="input-group fixed-width-md center">
                                <input type="text" class="filter datepicker date-input form-control" id="pspickinglist_ddw_to" name="pspickinglist_ddw_to"  placeholder="{l s='To' mod='pspickinglist'}" />
                                <input type="hidden" id="pspickinglist_ddw_to_alt" name="pspickinglist_ddw_to_alt" value="">
                                <span class="input-group-addon">
                                    <i class="icon-calendar"></i>
                                </span>
                            </div>
                        </div>
                        <div class="pull-left">&nbsp;</div>
                        <div class="pull-left">
                            <button class="btn btn-default" type="submit">
                                <i class="icon-filter"></i>
                                {l s='Filter by delivery date' mod='pspickinglist'}
                            </button>
                        </div>
                    
                    </form>
                    
                    {if $ddw_date_from || $ddw_date_to}
                    
                        <div class="pull-left">&nbsp;</div>
                        <div class="pull-left">
                            <a href="{$controller_link}" class="btn btn-default">
                                <i class="icon-refresh"></i>
                                {l s='Reset' mod='pspickinglist'}
                            </a>
                        </div>
                  
                    {/if}

                {/if}
            </div>
                
	        
            </div>

            
            <form method="post" action="{$action_link|escape:'htmlall':'UTF-8'}" name="generate_pspickinglist">

               {include file="./form_inputs.tpl" }

                <table class="table text-center" id="pickinglist_table">
                    <thead>
                    <tr>
                        <th class="text-center"><input type="checkbox" name="checkme" class="noborder" id="ckeckAll" /></th>
                        <th class="text-center">{l s='ID' mod='pspickinglist'}</th>
                        <th class="text-center">{l s='Customer' mod='pspickinglist'}</th>
                        <th class="text-center">{l s='Date' mod='pspickinglist'}</th>
                        {if $is_ddw_enabled || $is_fspickupatstorecarrier_enabled || $is_psd_enabled || $is_prestatilldrive_enabled || $is_prestatillhd_enabled}
                             <th class="text-center">
                                {if $is_fspickupatstorecarrier_enabled || $is_prestatilldrive_enabled || $is_prestatillhd_enabled}
                                    {l s='Delivery/Pickup Date' mod='pspickinglist'}
                                {else}
                                    {l s='Delivery Date' mod='pspickinglist'}
                                {/if}
                            </th>
                        {/if}
                        <th class="text-center">{l s='State' d='Admin.Global'}</th>
                        <th class="text-center">{l s='Total price' mod='pspickinglist'}</th>
                        <th class="text-center">{l s='Weight' mod='pspickinglist'}</th>
                        <th class="text-center">{l s='Payment' mod='pspickinglist'}</th>
                        <th class="text-center">{l s='Carrier' mod='pspickinglist'}</th>
                        {if false != $id_shops}
                             <th class="text-center">{l s='Shop' d='Admin.Global'}</th>
                        {/if}
                        <th class="text-center" colspan="2">{l s='Details' mod='pspickinglist'}</th>
                    </tr>
                    </thead>

                    <tbody>

                    {assign var=irow value=0}

                    {foreach from=$orders item=order name=orders}

                    {* <pre>{$order|@print_r}</pre> *}
                        {assign var=weight value=0}
                        {assign var=irow value=$irow+1}

                        <tr class="{if $irow is odd} odd {else}  {/if}">
                            <td>
                                <input type="checkbox" name="orders_ids[]"  id="orders_ids{$order.id_order|escape:'htmlall':'UTF-8'}" value="{$order.id_order|escape:'htmlall':'UTF-8'}" checked/>
                            </td>
                            <td>
                                {$order.id_order|escape:'htmlall':'UTF-8'}</td>
                            <td>
                                <label for="orders_ids{$order.id_order|escape:'htmlall':'UTF-8'}" class="t">{$order.firstname|escape:'htmlall':'UTF-8'} {$order.lastname|escape:'htmlall':'UTF-8'}</label>
                            </td>
                            <td>
                                <span class="date" style="display:none">{$order.date_add|escape:'htmlall':'UTF-8'}</span> 
                                {dateFormat date=$order.date_add full=1}
                            </td>
                            {if $is_ddw_enabled || $is_fspickupatstorecarrier_enabled || $is_psd_enabled || $is_prestatilldrive_enabled || $is_prestatillhd_enabled}
                                <td>
                                    <span class="ddw_date" style="display:none">{$order.ddw_order_date|escape:'htmlall':'UTF-8'}</span> 
                                    {dateFormat date=$order.ddw_order_date full=0}
                                </td>
                            {/if}
                            <td>
                                <span class="label color_field" style="background:{$order.state.color|escape:'htmlall':'UTF-8'}">{$order.state.name|escape:'htmlall':'UTF-8'}</span>
                            </td>
                            <td>
                                {$order.total_paid|escape:'htmlall':'UTF-8'|string_format:"%.2f"}&nbsp;{$order.currency_sign|escape:'htmlall':'UTF-8'}
                            </td>
                            <td>
                                {$order.weight|escape:'htmlall':'UTF-8'} {$weight_unit_display|escape:'htmlall':'UTF-8'}
                            </td>
                            <td>
                                {$order.payment|escape:'htmlall':'UTF-8'}
                            </td>
                            <td>
                                {$order.carrier|escape:'htmlall':'UTF-8'}
                            </td>
                             {if false != $id_shops}
                                <td> {$order.shop_name|escape:'htmlall':'UTF-8'}</td>
                            {/if}
                            <td class="pointer text-center">
							    <span class="btn-group-action">
                                    <span class="btn-group">
                                        <a class="btn btn-default _blank" href="{$link->getAdminLink('AdminPdf')|escape:'htmlall':'UTF-8'}&submitAction=generateInvoicePDF&id_order={$order.id_order|escape:'htmlall':'UTF-8'}" target="_blank">
                                            <i class="icon-file-text"></i>
                                        </a>
                                    </span>
                                </span>

                                <a class="btn btn-default" href="?tab=AdminOrders&id_order={$order.id_order|escape:'htmlall':'UTF-8'}&vieworder&token={$token|escape:'htmlall':'UTF-8'}" target="_blank">
                                    <i class="icon-search-plus"></i>
                                    {l s='Details' mod='pspickinglist'}
                                </a>
                            </td>
                          
                    {/foreach}
                    </tbody>
                </table>

                {include file="./form_inputs.tpl" }
                
            </form>

        </div>
                               
    </div>


    {else}
        <p>
            {l s='No orders available.' mod='pspickinglist'}
        </p>

    {/if}  

    

    <div class="alert alert-success "  id="pspickinglist_result_wrap" role="alert" style="display:none;">
        <p>{l s='The pickinglist has been generated as PDF file' mod='pspickinglist'}<br /><br>
        <a id="pspickinglist_pdf" href="" target="_blank"></a></p>
        <div id="pspickinglist_result"></div>
    </div>
 
</div>
