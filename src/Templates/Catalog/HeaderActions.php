<div class="admin-page-header__actions">
    <?php if (!empty($overview)) { ?>
        <a class="button button-publish" href="/Flexgrid/AdminInvoiceCatalog/items?new=1">Nieuw factuuritem</a>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoiceCatalog/items?type=group">Nieuwe keuzegroep</a>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoice/invoices">Naar facturen</a>
    <?php } else { ?>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoiceCatalog/items">Terug naar factuuritems</a>
    <?php } ?>
</div>
