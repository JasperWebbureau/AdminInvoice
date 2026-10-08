<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<grid class="admin-invoice-editor-layout">
    <div class="admin-invoice-customer-selector-slot" style="--cw:12">
        <?=$customerSelector ?? ''?>
    </div>
    <form id="admin-invoice-details-form" class="admin-form admin-invoice-details" style="--cw:12" data-admin-invoice-customer-form ajax="true" action="<?=$h($actions['update'] ?? '')?>" method="post">
        <input type="hidden" name="public_id" value="<?=$h($publicId)?>">
        <input type="hidden" name="customer[source_public_id]" value="<?=$h($customer['source_public_id'] ?? '')?>">

        <grid>
        <section class="panel admin-panel admin-invoice-details-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-file-invoice"></i> Factuurgegevens</h3></div>
            <grid class="panel__body admin-panel__body admin-form-grid fluid">
                <label class="admin-field"><span>Factuurnummer</span><input value="<?=$h($displayNumber)?>" readonly></label>
                <?php if (($sourceQuoteUrl ?? '') !== '') { ?><div style="--cw:12"><a href="<?=$h($sourceQuoteUrl)?>"><i class="fas fa-file-signature"></i> Bekijk bronofferte</a></div><?php } ?>
                <label class="admin-field"><span>Factuurdatum *</span><input type="date" name="issue_date" value="<?=$h($invoice->getIssueDate()->getValue())?>" required></label>
                <label class="admin-field"><span>Vervaldatum *</span><input type="date" name="due_date" value="<?=$h($invoice->getDueDate()->getValue())?>" required></label>
                <label class="admin-field"><span>Referentie / project</span><input name="customer_reference" value="<?=$h($invoice->getCustomerReference())?>" maxlength="128"></label>
                <label class="admin-field admin-field--wide"><span>Opmerking</span><textarea name="notes" rows="4"><?=$h($invoice->getNotes())?></textarea></label>
            </grid>
        </section>

        <section class="panel admin-panel admin-invoice-customer-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-user"></i> Klantgegevens</h3><?php if (($customerEditUrl ?? '') !== '') { ?><a class="button button-secondary button-icon" href="<?=$h($customerEditUrl)?>" target="_blank" rel="noopener" title="Klant bewerken" aria-label="Klant bewerken"><i class="fas fa-pencil"></i></a><?php } ?></div>
            <grid class="panel__body admin-panel__body admin-form-grid fluid">
                <label class="admin-field admin-field--wide"><span>Naam *</span><input name="customer[name]" value="<?=$h($customer['name'] ?? '')?>" required></label>
                <label class="admin-field"><span>Contactpersoon</span><input name="customer[contact_name]" value="<?=$h($customer['contact_name'] ?? '')?>"></label>
                <label class="admin-field"><span>E-mailadres</span><input type="email" name="customer[email]" value="<?=$h($customer['email'] ?? '')?>"></label>
                <label class="admin-field"><span>Telefoon</span><input name="customer[phone]" value="<?=$h($customer['phone'] ?? '')?>"></label>
                <label class="admin-field"><span>KvK</span><input name="customer[registration_number]" value="<?=$h($customer['registration_number'] ?? '')?>"></label>
                <label class="admin-field"><span>Btw-nummer</span><input name="customer[tax_number]" value="<?=$h($customer['tax_number'] ?? '')?>"></label>
                <label class="admin-field admin-field--wide"><span>Adres *</span><input name="address[line_1]" value="<?=$h($address['line_1'] ?? '')?>" required></label>
                <label class="admin-field admin-field--wide"><span>Adresregel 2</span><input name="address[line_2]" value="<?=$h($address['line_2'] ?? '')?>"></label>
                <label class="admin-field"><span>Postcode *</span><input name="address[postal_code]" value="<?=$h($address['postal_code'] ?? '')?>" required></label>
                <label class="admin-field"><span>Plaats *</span><input name="address[city]" value="<?=$h($address['city'] ?? '')?>" required></label>
                <label class="admin-field"><span>Regio</span><input name="address[region]" value="<?=$h($address['region'] ?? '')?>"></label>
                <label class="admin-field"><span>Landcode *</span><input name="address[country_code]" value="<?=$h($address['country_code'] ?? '')?>" maxlength="2" required></label>
                <?php if (($customerEditUrl ?? '') !== '') { ?><div style="--cw:12"><button class="button button-secondary button-small" type="button" ajax="true" action="<?=$h($actions['refresh_customer'] ?? '')?>" public_id="<?=$h($publicId)?>" alert="Klantgegevens uit de klantkaart overnemen? Niet-opgeslagen wijzigingen in dit formulier gaan verloren." use-waiting-icon><i class="fas fa-sync-alt"></i> Klantgegevens vernieuwen</button></div><?php } ?>
            </grid>
        </section>
        </grid>
        <div style="--cw:12"><button class="button button-publish" type="submit"><i class="fas fa-save"></i> Opslaan</button></div>
    </form>

    <section class="panel admin-panel admin-invoice-lines-panel" style="--cw:9;--cw-sm:12;--cw-xs:12">
        <div class="panel__header admin-panel__header"><h3><i class="fas fa-table-cells"></i> Factuurregels</h3></div>
        <div class="panel__body admin-panel__body admin-panel__body--flush">
            <div class="admin-invoice-line-form">
                <div>
                    <strong>Opgeslagen factuuritem toevoegen</strong>
                    <p>Neem een standaardregel over. Je kunt de gekopieerde omschrijving, prijs en btw daarna nog op dit concept aanpassen.</p>
                </div>
                <?php if (($catalogItems ?? []) !== []) { ?>
                    <form class="admin-form" ajax="true" action="<?=$h($actions['add_catalog_item'] ?? '')?>" method="post">
                        <input type="hidden" name="public_id" value="<?=$h($publicId)?>">
                        <label class="admin-field"><span>Factuuritem</span><select name="catalog_item_public_id">
                            <?php foreach ($catalogItems as $item) { ?>
                                <option value="<?=$h($item['public_id'])?>"><?=$h($item['name'] . ' · ' . $item['currency'] . ' ' . $item['unit_price'])?></option>
                            <?php } ?>
                        </select></label>
                        <button class="button button-secondary" type="submit">Item overnemen</button>
                    </form>
                <?php } else { ?><p>Er zijn nog geen beschikbare items. <a href="/Flexgrid/AdminInvoiceCatalog/items">Maak een factuuritem aan</a> of voeg hieronder handmatig een regel toe.</p><?php } ?>
            </div>
            <?php if ($lines === []) { ?>
                <div class="admin-empty-state admin-empty-state--compact"><i class="fas fa-receipt"></i><p>Nog geen factuurregels. Voeg hieronder de eerste regel toe.</p></div>
            <?php } else { ?>
                <div class="admin-invoice-lines-table-wrap">
                    <table class="admin-invoice-lines-table">
                        <thead><tr><th>Omschrijving</th><th>Aantal</th><th>Eenheid</th><th>Prijs excl.</th><th>Btw</th><th>Totaal</th><th><span class="admin-visually-hidden">Acties</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line) { ?>
                            <?php $lineFormId = 'admin-invoice-line-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$line['public_id']); ?>
                            <tr>
                                <td><input class="admin-invoice-line-input admin-invoice-line-input--description" form="<?=$h($lineFormId)?>" name="description" value="<?=$h($line['description'])?>" required maxlength="500" data-admin-invoice-line-field aria-label="Omschrijving"><?php if (($line['catalog_item_public_id'] ?? '') !== '') { ?><small>Overgenomen uit factuuritems</small><?php } ?></td>
                                <td><input class="admin-invoice-line-input" form="<?=$h($lineFormId)?>" name="quantity" value="<?=$h($line['quantity'])?>" required inputmode="decimal" data-admin-invoice-line-field aria-label="Aantal"></td>
                                <td><input class="admin-invoice-line-input" form="<?=$h($lineFormId)?>" name="unit" value="<?=$h($line['unit'])?>" required maxlength="32" data-admin-invoice-line-field aria-label="Eenheid"></td>
                                <td><input class="admin-invoice-line-input" form="<?=$h($lineFormId)?>" name="unit_price" value="<?=$h($line['unit_price'])?>" required inputmode="decimal" data-admin-invoice-line-field aria-label="Prijs exclusief btw"></td>
                                <td><select class="admin-invoice-line-input" form="<?=$h($lineFormId)?>" name="tax_choice" data-admin-invoice-line-field aria-label="Btw-tarief"><?php foreach ($taxOptions as $option) { ?><?php $choice = $option['rate'] . '|' . $option['category']; ?><option value="<?=$h($choice)?>"<?=$line['tax_choice'] === $choice ? ' selected' : ''?>><?=$h($option['label'])?></option><?php } ?></select></td>
                                <td><strong><?=$h($line['gross'])?></strong></td>
                                <td class="admin-invoice-line-actions">
                                    <form id="<?=$h($lineFormId)?>" ajax="true" action="<?=$h($actions['update_line'] ?? '')?>" method="post" data-admin-invoice-line-form>
                                        <input type="hidden" name="public_id" value="<?=$h($publicId)?>">
                                        <input type="hidden" name="line_id" value="<?=$h($line['public_id'])?>">
                                    </form>
                                    <button class="admin-invoice-line-remove" type="button" ajax="true" action="<?=$h($actions['remove_line'] ?? '')?>" public_id="<?=$h($publicId)?>" line_id="<?=$h($line['public_id'])?>" use-waiting-icon alert="Deze factuurregel verwijderen?" aria-label="Regel verwijderen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <form class="admin-form admin-invoice-line-form" ajax="true" action="<?=$h($actions['add_line'] ?? '')?>" method="post">
                <input type="hidden" name="public_id" value="<?=$h($publicId)?>">
                <label class="admin-field admin-invoice-line-description"><span>Omschrijving *</span><input name="description" required maxlength="500"></label>
                <label class="admin-field"><span>Aantal *</span><input name="quantity" value="1" required inputmode="decimal"></label>
                <label class="admin-field"><span>Eenheid *</span><input name="unit" value="uur" required maxlength="32"></label>
                <label class="admin-field"><span>Prijs excl. *</span><input name="unit_price" value="0,00" required inputmode="decimal"></label>
                <label class="admin-field"><span>Btw *</span><select name="tax_choice"><?php foreach ($taxOptions as $option) { ?><option value="<?=$h($option['rate'] . '|' . $option['category'])?>"><?=$h($option['label'])?></option><?php } ?></select></label>
                <button class="button button-secondary" type="submit"><i class="fas fa-plus"></i> Regel toevoegen</button>
            </form>
        </div>
    </section>

    <aside class="panel admin-panel admin-invoice-totals-panel" style="--cw:3;--cw-sm:12;--cw-xs:12">
        <div class="panel__header admin-panel__header"><h3><i class="fas fa-calculator"></i> Totaal</h3></div>
        <div class="panel__body admin-panel__body admin-invoice-totals">
            <div><span>Subtotaal</span><strong><?=$h($netTotal)?></strong></div>
            <?php foreach ($taxRows as $row) { ?><div><span><?=$h($row['label'])?></span><strong><?=$h($row['value'])?></strong></div><?php } ?>
            <div class="admin-invoice-totals__grand"><span>Totaal</span><strong><?=$h($grossTotal)?></strong></div>
        </div>
    </aside>
</grid>
