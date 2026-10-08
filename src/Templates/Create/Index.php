<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="admin-page admin-invoice-create">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow"><?=t('admin_invoice_eyebrow', 'Facturen')?></p>
            <h1><?=t('admin_invoice_create_title', 'Nieuwe factuur')?></h1>
            <p class="admin-page-header__intro"><?=t('admin_invoice_create_intro', 'Leg eerst de basisgegevens vast. Daarna kun je regels toevoegen en de factuur verder bewerken.')?></p>
        </div>
    </div>

    <?=$customerSelector ?? ''?>

    <?php if (trim((string)($prefillNotice ?? '')) !== '') { ?>
        <div class="notification notification--error" role="alert"><?=$h($prefillNotice)?></div>
    <?php } ?>

    <form class="admin-form admin-invoice-create-form" data-admin-invoice-customer-form ajax="true" action="<?=$h($createAction ?? '')?>" method="post">
        <input type="hidden" name="customer[source_public_id]" value="<?=$h($customer['source_public_id'] ?? '')?>">
        <grid>
        <section class="panel admin-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-user"></i> <?=t('admin_invoice_customer_details', 'Klantgegevens')?></h3></div>
            <grid class="panel__body admin-panel__body admin-form-grid fluid">
                <label class="admin-field admin-field--wide"><span>Bedrijfs- of klantnaam *</span><input name="customer[name]" value="<?=$h($customer['name'] ?? '')?>" required maxlength="255" autocomplete="organization"></label>
                <label class="admin-field"><span>Contactpersoon</span><input name="customer[contact_name]" value="<?=$h($customer['contact_name'] ?? '')?>" maxlength="255" autocomplete="name"></label>
                <label class="admin-field"><span>E-mailadres</span><input type="email" name="customer[email]" value="<?=$h($customer['email'] ?? '')?>" maxlength="255" autocomplete="email"></label>
                <label class="admin-field"><span>Telefoon</span><input name="customer[phone]" value="<?=$h($customer['phone'] ?? '')?>" maxlength="64" autocomplete="tel"></label>
                <label class="admin-field"><span>KvK-nummer</span><input name="customer[registration_number]" value="<?=$h($customer['registration_number'] ?? '')?>" maxlength="64"></label>
                <label class="admin-field"><span>Btw-nummer</span><input name="customer[tax_number]" value="<?=$h($customer['tax_number'] ?? '')?>" maxlength="64"></label>
            </grid>
        </section>

        <section class="panel admin-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-location-dot"></i> <?=t('admin_invoice_billing_address', 'Factuuradres')?></h3></div>
            <grid class="panel__body admin-panel__body admin-form-grid fluid">
                <label class="admin-field admin-field--wide"><span>Adres *</span><input name="address[line_1]" value="<?=$h($address['line_1'] ?? '')?>" required maxlength="255" autocomplete="street-address"></label>
                <label class="admin-field admin-field--wide"><span>Adresregel 2</span><input name="address[line_2]" value="<?=$h($address['line_2'] ?? '')?>" maxlength="255"></label>
                <label class="admin-field"><span>Postcode *</span><input name="address[postal_code]" value="<?=$h($address['postal_code'] ?? '')?>" required maxlength="32" autocomplete="postal-code"></label>
                <label class="admin-field"><span>Plaats *</span><input name="address[city]" value="<?=$h($address['city'] ?? '')?>" required maxlength="128" autocomplete="address-level2"></label>
                <label class="admin-field"><span>Regio</span><input name="address[region]" value="<?=$h($address['region'] ?? '')?>" maxlength="128" autocomplete="address-level1"></label>
                <label class="admin-field"><span>Landcode *</span><input name="address[country_code]" value="<?=$h($address['country_code'] ?? 'NL')?>" required maxlength="2" pattern="[A-Za-z]{2}" autocomplete="country"></label>
            </grid>
        </section>

        <section class="panel admin-panel" style="--cw:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-file-invoice"></i> <?=t('admin_invoice_invoice_details', 'Factuurgegevens')?></h3></div>
            <grid class="panel__body admin-panel__body admin-form-grid fluid">
                <label class="admin-field"><span>Factuurdatum *</span><input type="date" name="issue_date" value="<?=$h($issueDate ?? '')?>" required></label>
                <label class="admin-field"><span>Vervaldatum *</span><input type="date" name="due_date" value="<?=$h($dueDate ?? '')?>" required></label>
                <label class="admin-field"><span>Valuta *</span><select name="currency"><option value="EUR">EUR</option></select></label>
                <label class="admin-field"><span>Referentie / project</span><input name="customer_reference" maxlength="128"></label>
                <label class="admin-field admin-field--wide"><span>Opmerking</span><textarea name="notes" rows="4"></textarea></label>
            </grid>
        </section>
        </grid>

        <div class="admin-form-actions">
            <a class="button button-secondary" href="<?=$h($overviewUrl ?? '')?>">Annuleren</a>
            <button class="button button-publish" type="submit"><i class="fas fa-arrow-right"></i> Concept maken</button>
        </div>
    </form>
</section>
