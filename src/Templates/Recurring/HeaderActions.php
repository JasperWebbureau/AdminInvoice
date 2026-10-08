<div class="admin-page-header__actions">
    <?php if ($showEditor ?? false) { ?>
        <a class="button button-secondary" href="/Flexgrid/AdminRecurringInvoice/plans">Terug naar schema's</a>
    <?php } else { ?>
        <a class="button button-publish" href="/Flexgrid/AdminRecurringInvoice/create">Nieuw herhaalschema</a>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoiceCatalog/items">Factuuritems</a>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoice/invoices">Facturen bekijken</a>
        <a class="button button-secondary" href="/Flexgrid/AdminInvoiceProcessing/index">Factuurverwerking</a>
    <?php } ?>
</div>
