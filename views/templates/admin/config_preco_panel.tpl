<div class="panel" id="config_preco_panel">
    <div class="panel-heading"><i class="icon-eye-open"></i> {l s='Détail de la précommande' mod='alo_preco'}</div>
    <div class="content">
        <p><strong>{l s='Nom :' mod='alo_preco'}</strong> {$alo_preco_config->name}</p>
        <p><strong>{l s='Nombre de ventes à atteindre :' mod='alo_preco'}</strong> {$alo_preco_config->nb_target}</p>
        <p><strong>{l s='Boost / Nombre à incrémenter :' mod='alo_preco'}</strong> {$alo_preco_config->incrementer}</p>
        <p><strong>{l s='Quantité actuelle (avec incrémenteur) :' mod='alo_preco'}</strong> {$alo_preco_config->getNbCurrent()}</p>
        <p><strong>{l s='Ouverture des précommande :' mod='alo_preco'}</strong> {Tools::displayDate($alo_preco_config->date_begin)}</p>
        <p><strong>{l s='Date de rappel :' mod='alo_preco'}</strong> {Tools::displayDate($alo_preco_config->date_reminder)}</p>
        <p><strong>{l s='Pourcentage actuel :' mod='alo_preco'}</strong> {$alo_preco_config->getVolumePct()} %</p>
        <p><strong>{l s='Fin des précommandes :' mod='alo_preco'}</strong> {Tools::displayDate($alo_preco_config->date_end)}</p>
    </div><!-- /.content -->
    <div class="panel-footer">
        <a class="btn btn-default"
           id="alo_preco_btn_1"
           href="{$link->getAdminLink('AdminAloPrecoConfig')}">
            {l s='Liste des configurations de précommandes' mod='alo_preco'}
        </a>
        {if Tools::getValue('controller') != 'AdminAloPrecoPreorder'}
            <a class="btn btn-default"
               id="alo_preco_btn_2"
               href="{$link->getAdminLink('AdminAloPrecoPreorder')}&{AloPrecoConfig::PRIMARY_KEY}={$alo_preco_config->id}">
                {l s='Commandes en précommandes' mod='alo_preco'}
            </a>
        {/if}
        {if Tools::getValue('controller') != 'AdminAloPrecoConfigProductAttribute'}
            <a class="btn btn-default"
               id="alo_preco_btn_3"
               href="{$link->getAdminLink('AdminAloPrecoConfigProductAttribute')}&{AloPrecoConfig::PRIMARY_KEY}={$alo_preco_config->id}">
                {l s='Produits en précommandes' mod='alo_preco'}
            </a>
        {/if}
        <a class="btn btn-default"
           id="alo_preco_btn_4"
           href="{$link->getAdminLink('AdminAloPrecoConfig')}&update{AloPrecoConfig::TABLE_NAME}=1&{AloPrecoConfig::PRIMARY_KEY}={$alo_preco_config->id}">
                {l s='Modifier la configuration préco' mod='alo_preco'}
        </a>
    </div>
</div>
