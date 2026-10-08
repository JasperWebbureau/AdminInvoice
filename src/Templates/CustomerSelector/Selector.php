<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$selectedId = (string)($currentCustomer['source_public_id'] ?? '');
$selectedName = (string)($currentCustomer['name'] ?? '');
?>
<grid>
<section class="panel admin-panel admin-invoice-customer-selector" style="--cw:12" data-admin-invoice-customer-selector>
    <div class="panel__header admin-panel__header"><h3><i class="fas fa-address-book"></i> Bestaande klant selecteren</h3></div>
    <div class="panel__body admin-panel__body">
        <form class="admin-form admin-invoice-customer-search" data-admin-invoice-customer-search ajax="true" action="<?=$h($searchAction ?? '')?>" method="post">
            <label class="admin-data-search">
                <span class="admin-visually-hidden">Zoek een bestaande klant</span>
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" name="q" placeholder="Zoek op klant, contact, e-mail, adres of nummer…" autocomplete="off">
            </label>
            <button class="button button-secondary" type="submit">Zoeken</button>
        </form>
        <div class="admin-invoice-customer-selected<?=$selectedId !== '' ? ' is-selected' : ''?>" data-admin-invoice-customer-selected>
            <span data-admin-invoice-customer-selected-label><?=$selectedId !== '' ? 'Gekoppeld aan ' . $h($selectedName) : 'Handmatige klantgegevens'?></span>
            <button type="button" class="button button-secondary" data-admin-invoice-customer-clear<?=$selectedId === '' ? ' hidden' : ''?>>Koppeling wissen</button>
        </div>
        <div class="admin-invoice-customer-results" data-admin-invoice-customer-results aria-live="polite"></div>
    </div>
</section>
</grid>
