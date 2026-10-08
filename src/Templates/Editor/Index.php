<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="admin-page admin-invoice-editor-page">
    <div class="admin-page-header admin-invoice-editor-header">
        <div>
            <p class="admin-page-header__eyebrow"><?=t('admin_invoice_single', 'Factuur')?></p>
            <h1><?=t('admin_invoice_edit_title', 'Factuur bewerken')?></h1>
        </div>
        <div class="admin-page-header__actions">
            <span class="admin-status is-warning"><i class="fas fa-circle" aria-hidden="true"></i> <?=$h($statusLabel ?? 'Concept')?></span>
        </div>
    </div>

    <p>Bekijk eerst de opgeslagen versie als concept-PDF. Dit maakt de factuur niet definitief en geeft nog geen factuurnummer. Sla gewijzigde factuurgegevens eerst op.</p>

    <div data-admin-invoice-editor>
        <?=$content ?? ''?>
    </div>
</section>
