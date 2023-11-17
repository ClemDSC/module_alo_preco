<div class="panel">
    <div class="panel-heading"><i class="icon-info"></i> {l s='Information' mod='alo_preco'}</div>
    <div class="form-wrapper">
        <div class="row">
            <h4><strong><u>{l s='Catégorie "précommande" :' mod='alo_preco'}</u></strong></h4>
            <p>
                <strong>set_preco_products_category :</strong> <code>{$category_cron_url}</code><br>
                {l s='CRON pour la mise en place des produits dans la catégorie "précommande".' mod='alo_preco'}
            </p>
        </div>
        <div class="row">
            <h4><strong><u>{l s='Traitement des commandes en préco :' mod='alo_preco'}</u></strong></h4>
            <p>
                <strong>process_preorders :</strong> <code>{$process_preorders}</code><br>
                CRON général pour le traitement des précommandes. Ce CRON lance successivement <strong>"send_preorder_reminder_emails"</strong> et <strong>"close_preorders"</strong>
            </p>
            <p>
                <strong>send_preorder_reminder_emails :</strong> <code>{$send_preorder_reminder_emails}</code><br>
                {l s='CRON pour l\'envoi des mails de rappel.' mod='alo_preco'}
            </p>
            <p>
                <strong>close_preorders :</strong> <code>{$close_preorders}</code><br>
                {l s='CRON pour la validation ou échec des précommandes.' mod='alo_preco'}
            </p>
        </div>
    </div><!-- /.form-wrapper -->
    <div class="panel-footer">
    </div>
</div>
