<?php $h = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }; ?>
<div class="admin-page-header__actions">
    <a class="button button-secondary" href="<?=$h($overviewUrl ?? '')?>"><i class="fas fa-arrow-left"></i> Terug naar overzicht</a>
    <?php include __DIR__ . '/PreviewButton.php'; ?>
    <?php include __DIR__ . '/FinalizeButton.php'; ?>
    <button class="button button-secondary admin-invoice-delete-draft" type="button" ajax="true" action="<?=$h($deleteAction ?? '')?>" public_id="<?=$h($publicId ?? '')?>" alert="Dit concept en alle factuurregels definitief verwijderen?" use-waiting-icon>
        <i class="fas fa-trash" aria-hidden="true"></i> Concept verwijderen
    </button>
</div>
