<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="admin-page admin-invoice-detail-page admin-credit-note-page">
    <div class="admin-page-header admin-invoice-editor-header">
        <div>
            <p class="admin-page-header__eyebrow">Definitieve creditnota</p>
            <h1><?=$h($displayNumber ?? '')?></h1>
        </div>
        <div class="admin-page-header__actions">
            <span class="admin-status is-success"><i class="fas fa-lock" aria-hidden="true"></i> Definitief</span>
        </div>
    </div>

    <div data-admin-credit-note-detail>
        <?=$content ?? ''?>
    </div>
</section>
