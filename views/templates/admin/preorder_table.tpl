<div class="row">
    <p>#{$product_attribute_id} - {$product_attribute_name}</p>
</div>
<hr>
<div class="row">
    <table class="table">
        <thead>
        <tr>
            <th>{l s='ID Commande' mod='alo_preco'}</th>
            <th>{l s='ID Préco' mod='alo_preco'}</th>
            <th>{l s='Date début préco' mod='alo_preco'}</th>
            <th>{l s='Date fin préco' mod='alo_preco'}</th>
            <th>{l s='Date de l\'ajout' mod='alo_preco'}</th>
            <th>{l s='Nom de la précommande' mod='alo_preco'}</th>
            <th>{l s='Marque' mod='alo_preco'}</th>
            <th>{l s='Nom du produit' mod='alo_preco'}</th>
            <th>{l s='Référence produit' mod='alo_preco'}</th>
            <th>{l s='Quantité du produit' mod='alo_preco'}</th>
            <th>{l s='ID du produit' mod='alo_preco'}</th>
            <th>{l s='ID de déclinaison du produit' mod='alo_preco'}</th>
            <th>{l s='Référence produit' mod='alo_preco'}</th>
            <th>{l s='Couleur' mod='alo_preco'}</th>
            <th>{l s='Taille' mod='alo_preco'}</th>
            <th>{l s='Nom du client' mod='alo_preco'}</th>
            <th>{l s='Email du client' mod='alo_preco'}</th>
            <th>{l s='Pays de livraison' mod='alo_preco'}</th>
            <th>{l s='Livraison' mod='alo_preco'}</th>
        </tr>
        </thead>
        <tbody>
            {assign var="total" value=0}
            {foreach from=$preorder_list item=preorder}
                {if $preorder.product_attribute_id == $product_attribute_id}
                    {assign var="total" value=$total+$preorder.product_quantity}
                    <tr>
                        <td>{$preorder.id_order}</td>
                        <td>{$preorder.id_config}</td>
                        <td>{Tools::displayDate($preorder.preco_config_date_begin)}</td>
                        <td>{Tools::displayDate($preorder.preco_config_date_end)}</td>
                        <td>{Tools::displayDate($preorder.date_add)}</td>
                        <td>{$preorder.preco_config_name}</td>
                        <td>{$preorder.manufacturer_name}</td>
                        <td>{$preorder.product_name}</td>
                        <td>{$preorder.product_reference}</td>
                        <td>{$preorder.product_quantity}</td>
                        <td>{$preorder.product_id}</td>
                        <td>{$preorder.product_attribute_id}</td>
                        <td>{$preorder.product_reference}</td>
                        <td>{$preorder.color_name}</td>
                        <td>{$preorder.size_name}</td>
                        <td>{$preorder.customer_name}</td>
                        <td>{$preorder.customer_email}</td>
                        <td>{$preorder.country_name}</td>
                        <td>{$preorder.carrier_name}</td>
                    </tr>
                {/if}
            {/foreach}
        </tbody>
        <tfoot>
        <tr>
            <th colspan="1" class="text-left">TOTAL</th>
            <td colspan="18">{$total}</td>
        </tr>
        </tfoot>
    </table>
</div>
<div class="row">
    <div class="col-lg-12">
        <a href="{$link->getAdminLink('AdminAloPrecoPreorder')|escape:'html':'UTF-8'}&getCSV=1&product_attribute_id={$product_attribute_id}&{AloPrecoConfig::PRIMARY_KEY}={$alo_preco_config->id}" class="btn btn-primary pull-right">
            <i class="icon-download"></i> {l s='Télécharger le CSV' mod='alo_preco'}
        </a>
    </div>
</div>
<br>
<br>
