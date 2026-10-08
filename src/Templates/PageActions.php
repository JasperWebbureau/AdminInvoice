<?php $h = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }; ?>
<div class="admin-page-header__actions">
    <?php if (($mode ?? '') === 'detail' || ($mode ?? '') === 'credit') { ?>
        <a class="button button-primary" href="<?=$h($pdfUrl ?? '')?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Open PDF</a>
    <?php } ?>
    <?php if (($mode ?? '') === 'detail' && ($canCredit ?? false)) { ?>
        <button class="button button-secondary admin-credit-open" type="button" data-admin-credit-open><i class="fas fa-file-circle-minus"></i> Volledig crediteren</button>
    <?php } ?>
    <?php if (($mode ?? '') === 'credit') { ?>
        <a class="button button-secondary" href="<?=$h($invoiceUrl ?? '')?>"><i class="fas fa-file-invoice"></i> Bronfactuur</a>
    <?php } ?>
    <a class="button button-secondary" href="<?=$h($overviewUrl ?? '')?>"><i class="fas fa-arrow-left"></i> Terug naar overzicht</a>
</div>
