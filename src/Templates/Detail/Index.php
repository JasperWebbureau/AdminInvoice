<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="admin-page admin-invoice-detail-page">
    <div class="admin-page-header admin-invoice-editor-header">
        <div>
            <p class="admin-page-header__eyebrow">Definitieve factuur</p>
            <h1><?=$h($displayNumber ?? '')?></h1>
        </div>
        <div class="admin-page-header__actions">
            <span class="admin-status is-success"><i class="fas fa-lock" aria-hidden="true"></i> <?=$h($statusLabel ?? 'Definitief')?></span>
        </div>
    </div>

    <div data-admin-invoice-detail>
        <?=$content ?? ''?>
    </div>

    <grid>
        <?=$dispatchPanel ?? ''?>
    </grid>

    <?php if (($detailExtensions ?? '') !== '') { ?>
        <grid class="admin-invoice-detail-extensions fluid" data-admin-invoice-detail-extensions>
            <?=$detailExtensions?>
        </grid>
    <?php } ?>

    <?php if ($canCredit ?? false) { ?>
        <dialog class="admin-credit-dialog" data-admin-credit-dialog aria-labelledby="admin-credit-title">
            <form class="admin-form admin-credit-form" ajax="true" action="<?=$h($creditAction ?? '')?>" method="post" use-waiting-icon>
                <input type="hidden" name="public_id" value="<?=$h($publicId ?? '')?>">
                <div class="admin-credit-dialog__header">
                    <div>
                        <p class="admin-page-header__eyebrow">Onomkeerbare correctie</p>
                        <h2 id="admin-credit-title">Factuur volledig crediteren</h2>
                    </div>
                    <button class="admin-credit-dialog__close" type="button" data-admin-credit-close aria-label="Sluiten"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="admin-credit-dialog__body">
                    <p>Er wordt direct een definitieve creditnota voor het volledige factuurbedrag gemaakt. De oorspronkelijke factuur blijft ongewijzigd bewaard.</p>
                    <label class="admin-field admin-credit-reason">
                        <span class="admin-field__label">Reden <span aria-hidden="true">*</span></span>
                        <textarea name="reason" rows="4" maxlength="1000" required placeholder="Bijvoorbeeld: opdracht geannuleerd"></textarea>
                    </label>
                </div>
                <div class="admin-credit-dialog__actions">
                    <button class="button button-secondary" type="button" data-admin-credit-close>Annuleren</button>
                    <button class="button button-publish" type="submit"><i class="fas fa-file-circle-minus"></i> Creditnota maken</button>
                </div>
            </form>
        </dialog>
    <?php } ?>
</section>
