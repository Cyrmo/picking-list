{** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}
 
 <span class="text-danger">🚫 {l s='Your store is in maintenance. The front controller responsible for the PDF generation is not reachable.' mod='pspickinglist'}
    <a class="text-danger" style="text-decoration:underline"  target="_blank" href="{Context::getContext()->link->getAdminLink('AdminMaintenance')|escape:'html':'UTF-8'}">
        {l s='Please enable the store if you want to use PDF format, or whitelist your server local IP:' mod='pspickinglist'} <code>{$local_ip|escape:'html':'UTF-8'}</code>
    </a>
</span>