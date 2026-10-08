<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<div class="admin-page-header__actions admin-invoice-main-actions">
    <a class="button button-secondary" href="/Flexgrid/AdminInvoice/reviewQueue">Concepten controleren</a>
    <a class="button button-secondary" href="/Flexgrid/AdminInvoiceCatalog/items">Factuuritems</a>
    <a class="button button-secondary" href="/Flexgrid/AdminRecurringInvoice/plans">Terugkerende facturen</a>
    <a class="button button-publish" href="<?=$h($createUrl ?? '')?>">
        <i class="fas fa-plus"></i>
        <?=t('admin_invoice_new', 'Nieuwe factuur')?>
    </a>
</div>
