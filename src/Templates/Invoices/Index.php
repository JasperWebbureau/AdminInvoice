<section class="admin-page admin-invoice-overview">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow"><?=t('admin_invoice_eyebrow', 'Facturen')?></p>
            <h1><?=t('admin_invoice_overview_title', 'Overzicht')?></h1>
            <p class="admin-page-header__intro"><?=t('admin_invoice_overview_intro', 'Maak factuurconcepten, controleer de regels en maak ze daarna definitief.')?></p>
        </div>
        <?php if (($mollieSyncAction ?? '') !== '') { ?>
            <div>
                <button class="button button-secondary" type="button" ajax="true" action="<?=htmlspecialchars($mollieSyncAction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>" data-admin-invoice-mollie-sync use-waiting-icon>Controleer Mollie-betalingen</button>
            </div>
        <?php } ?>
    </div>

    <div data-admin-invoice-content>
        <?=$content ?? ''?>
    </div>
</section>
