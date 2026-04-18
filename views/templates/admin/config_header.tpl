{** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}
 
 <div class="panel">
    <div class="panel-heading">{l s='Picking list' mod='pspickinglist'} {$version|escape:'htmlall':'UTF-8'}</div>
    <p>{$description|escape:'htmlall':'UTF-8'}</p>
    <p>{l s=' System requirements:' mod='pspickinglist'} 
    <br>
        {if $shop_enabled}
          <span class="text-success">✓ {l s='The front controller' mod='pspickinglist'} <code>{$base_url}/module/pspickinglist/Renderpdf</code> {l s='responsible of the PDF generation is reachable' mod='pspickinglist'} </span>
       {else}
           {include file="./shop_disabled_message.tpl" }
        {/if} 
        <br>
        {if $curl_enabled}
          <span class="text-success">✓ {l s='php_curl extension is installed' mod='pspickinglist'}</span>
       {else}
           
          <span class="text-danger">🚫 {l s='php_curl extension is required to use Pickinglist!' mod='pspickinglist'}</span>
        {/if} 
        
       </p>
    <a href="{$admin_link|escape:'htmlall':'UTF-8'}" class="btn btn-default">{l s='Go to orders picking list' mod='pspickinglist'}</a>
</div>