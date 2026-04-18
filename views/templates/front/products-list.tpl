  {** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}

<table class="product" width="100%" cellpadding="4" cellspacing="0">

	<thead>
	<tr>
		<th {if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_IMAGE')}colspan="2"{/if} class="product header small" >{l s='Product' mod='pspickinglist'}</th>

		{* <th class="product header small">{l s='Quantity' mod='pspickinglist'}</th> *}
		<th class="product header small">{l s='Quantity' mod='pspickinglist'}</th>

		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_REF')}
			<th class="product header small">{l s='Ref.' mod='pspickinglist'}</th>
		{/if}

		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_EAN13')}
			<th class="product header small">{l s='EAN-13/JAN' mod='pspickinglist'}</th>
		{/if}
		
		{if Configuration::get('PSPICKINGLIST_SHOW_STOCK_QUANTITY')}
			<th class="product header small">{l s='Remaining stock' mod='pspickinglist'}</th>
		{/if}

		{if Configuration::get('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY')}
			<th class="product header small">{l s='Physical stock' mod='pspickinglist'}</th>
		{/if}

		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY')}
			<th class="product header small">{l s='Category' mod='pspickinglist'}</th>
		{/if}

		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER')}
			<th class="product header small">{l s='Manufacturer' mod='pspickinglist'}</th>
		{/if}

		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF')}
			<th class="product header small">{l s='Supplier ref' mod='pspickinglist'}</th>
		{/if}
		{* {if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_WAREHOUSE')}
			<th class="product header small">{l s='Warehouse' mod='pspickinglist'}</th>
	   {/if} *}
		{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_LOCATION')}
			<th class="product header small">{l s='Warehouse location' mod='pspickinglist'}</th>
	   {/if}

		{if Configuration::get('PSPICKINGLIST_ORDER_REF_MODE') != 'hide'}
			<th class="product header small">{l s='In order(s)' mod='pspickinglist'}</th>
		{/if}
	</tr>
	</thead>

	<tbody>

	<!-- PRODUCTS -->
	{foreach $products as $products_group}
		{foreach $products_group.products as $product}


		{cycle values=["color_line_even", "color_line_odd"] assign=bgcolor_class}
		{assign var=color_line_even value="#FFFFFF"}
		{assign var=color_line_odd value="#F9F9F9"}
		<tr nobr="true" class="product {$bgcolor_class|escape:'htmlall':'UTF-8'}">
			
				{* <td class="product center">
					<div style="display:inline-block;width:25px!important:height:25px;border:1px solid #999"></div>
				</td> *}

			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_IMAGE')}
				
				<td class="product center">
					{if $product.image_tag}
						{if isset($product.image) && $product.image->id}
							{$product.image_tag}
						{/if}
					{/if}
				</td>
			{/if}
			<td class="product left">
				{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_ID')}
					{$product.product_id|escape:'htmlall':'UTF-8'} - 
				{/if}
				{$product.product_name|escape:'htmlall':'UTF-8'}
			</td>

			<td class="product center">
			 	{$product.product_quantity|escape:'htmlall':'UTF-8'}
			</td>
			
			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_REF')}
				<td class="product center">
					{$product.reference|escape:'htmlall':'UTF-8'}
				</td>
			{/if} 

			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_EAN13')}
				<td class="product center" style="vertical-align:top">
					{if $product.ean13 != "0" && $product.ean13 != "" && Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_BARCODE')}
						 <img src="https://barcode.inazerty.com?code={$product.ean13|escape:'htmlall':'UTF-8'}" width="190" height="60" alt="{$product.ean13|escape:'htmlall':'UTF-8'}" />
					{/if}

					{$product.ean13|escape:'htmlall':'UTF-8'}
					
				</td>
			{/if} 


			{if Configuration::get('PSPICKINGLIST_SHOW_STOCK_QUANTITY')}
				<td class="product center">
					{$product.stock_quantity|escape:'htmlall':'UTF-8'}
				</td>
			{/if}

			{if Configuration::get('PSPICKINGLIST_SHOW_STOCK_PHYSICAL_QUANTITY')}
				<td class="product center">
					{$product.stock_physical_quantity|escape:'htmlall':'UTF-8'}
				</td>
			{/if}
			

			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_CATEGORY')}
				<td class="product center">
					{$product.category_name|escape:'htmlall':'UTF-8'}
				</td>
			{/if}  
			
			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_MANUFACTURER')}
				<td class="product center">
					{$product.manufacturer_name|escape:'htmlall':'UTF-8'}
				</td>
			{/if}

           


			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_SUPPLIER_REF')}
            	<td class="product center">
					{foreach from=$product.suppliers_references item=element}
						{if $element.product_supplier_reference != ""}
							{$element.product_supplier_reference|escape:'htmlall':'UTF-8'}
							({$element.name|escape:'htmlall':'UTF-8'})
							<br>
						{/if}
					{/foreach}
					
				</td>
			{/if}
			{* {if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_WAREHOUSE')}
            	<td class="product center">{$product.id_warehouse|escape:'htmlall':'UTF-8'}</td>
			{/if} *}
			
			{if Configuration::get('PSPICKINGLIST_SHOW_PRODUCT_LOCATION')}
            	<td class="product center">{$product.location|escape:'htmlall':'UTF-8'}</td>
			{/if}

			{if Configuration::get('PSPICKINGLIST_ORDER_REF_MODE') != 'hide'}
			<td class="product center">
				{foreach from=$product.order_reference item=reference}
					<div style="line-height:1;margin:0">{$reference|escape:'htmlall':'UTF-8'}</div>
				{/foreach}
			</td>
			{/if}


		</tr>
		{/foreach}
	{/foreach}
	<!-- END PRODUCTS -->

	</tbody>

</table>
