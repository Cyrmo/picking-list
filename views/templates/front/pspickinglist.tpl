  {** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}

{* {$style_tab|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3} *}
{$style_tab}

<p style="margin-bottom:10px">
	{$products_count|escape:'htmlall':'UTF-8'}
	{l s='Products references' mod='pspickinglist'}
	({$products_sum|escape:'htmlall':'UTF-8'} {l s='items' mod='pspickinglist'})
	{l s='in' mod='pspickinglist'}
	{$orders_count|escape:'htmlall':'UTF-8'}
	{l s='orders' mod='pspickinglist'}
</p>

<table width="100%" id="body" border="0" cellpadding="0" cellspacing="0" style="margin:0;">


	<tr>

		<td colspan="12">
			{* {$products_tab|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3} *}
			{$products_tab}
		</td>
	</tr>

</table>
