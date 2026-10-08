<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<?php if (($query ?? '') === '') { ?>
    <p class="admin-invoice-customer-results__hint">Vul minimaal twee tekens in.</p>
<?php } elseif (($selections ?? []) === []) { ?>
    <p class="admin-invoice-customer-results__hint">Geen actieve klanten met een volledig factuuradres gevonden.</p>
<?php } else { ?>
    <div class="admin-invoice-customer-result-list">
        <?php foreach ($selections as $selection) { ?>
            <?php $party = $selection->getParty(); $address = $selection->getBillingAddress(); ?>
            <button
                type="button"
                class="admin-invoice-customer-result"
                data-admin-invoice-customer-result
                data-customer-public-id="<?=$h($selection->getPublicId())?>"
                data-customer-name="<?=$h($party['name'] ?? '')?>"
                data-customer-contact-name="<?=$h($party['contact_name'] ?? '')?>"
                data-customer-email="<?=$h($party['email'] ?? '')?>"
                data-customer-phone="<?=$h($party['phone'] ?? '')?>"
                data-customer-registration-number="<?=$h($party['registration_number'] ?? '')?>"
                data-customer-tax-number="<?=$h($party['tax_number'] ?? '')?>"
                data-address-line-1="<?=$h($address['line_1'] ?? '')?>"
                data-address-line-2="<?=$h($address['line_2'] ?? '')?>"
                data-address-postal-code="<?=$h($address['postal_code'] ?? '')?>"
                data-address-city="<?=$h($address['city'] ?? '')?>"
                data-address-region="<?=$h($address['region'] ?? '')?>"
                data-address-country-code="<?=$h($address['country_code'] ?? '')?>"
            >
                <strong><?=$h($party['name'] ?? '')?></strong>
                <span><?=trim((string)($party['contact_name'] ?? '')) !== '' ? $h($party['contact_name']) . ' · ' : ''?><?=$h(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? ''))?></span>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
        <?php } ?>
    </div>
<?php } ?>
