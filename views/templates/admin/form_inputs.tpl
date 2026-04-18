{** 
 * 2019 - 2021 inAzerty
 * module Pspickinglist
 * 
 * @author     inAzerty  <contact@inazerty.com>
 * @copyright  2019 - 2021 inAzerty
 * @license  commercial
 * @version  1.11.1 from 2021/03/11
 *}
 
<div>
    <input {if count($orders) < 1}disabled{/if} name="generate_pickinglist" type="submit" value="{l s='Generate pickinglist' mod='pspickinglist'}" class="btn btn-primary">
    <input type="hidden" name="id_employee" value="{$id_employee|escape:'htmlall':'UTF-8'}">

</div>

<hr>