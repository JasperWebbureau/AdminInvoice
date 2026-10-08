<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$addressLines = array_filter([
    $address['line_1'] ?? '',
    $address['line_2'] ?? '',
    trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')),
    $address['region'] ?? '',
    $address['country_code'] ?? '',
], function ($value): bool {
    return trim((string)$value) !== '';
});
?>
<grid class="admin-invoice-editor-layout admin-invoice-detail-layout">
    <section class="panel admin-panel admin-invoice-details-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
        <div class="panel__header admin-panel__header"><h3><i class="fas fa-file-invoice"></i> Factuurgegevens</h3></div>
        <div class="panel__body admin-panel__body admin-invoice-detail-list">
            <div><span>Factuurnummer</span><strong><?=$h($displayNumber)?></strong></div>
            <?php if (($sourceQuoteUrl ?? '') !== '') { ?><div><span>Bronofferte</span><strong><a href="<?=$h($sourceQuoteUrl)?>">Bekijk offerte</a></strong></div><?php } ?>
            <div><label for="admin-invoice-issue-date">Factuurdatum</label><input id="admin-invoice-issue-date" type="date" name="issue_date" value="<?=$h($invoice->getIssueDate()->getValue())?>" ajax="onchange" action="<?=$h($correctIssueDateAction ?? '')?>" public_id="<?=$h($invoice->getPublicId())?>" view="detail" aria-label="Factuurdatum aanpassen"></div>
            <div><span>Vervaldatum</span><strong><?=$h($invoice->getDueDate()->getValue())?></strong></div>
            <div><span>Definitief gemaakt</span><strong><?=$h($finalizedAt)?></strong></div>
            <?php if ($invoice->getCustomerReference() !== '') { ?><div><span>Referentie</span><strong><?=$h($invoice->getCustomerReference())?></strong></div><?php } ?>
        </div>
    </section>

    <section class="panel admin-panel admin-invoice-customer-panel" style="--cw:6;--cw-sm:12;--cw-xs:12">
        <div class="panel__header admin-panel__header"><h3><i class="fas fa-user"></i> Klant en factuuradres</h3></div>
        <div class="panel__body admin-panel__body admin-invoice-detail-list">
            <div><span>Klant</span><strong><?=$h($customer['name'] ?? '')?></strong></div>
            <?php if (($customer['contact_name'] ?? '') !== '') { ?><div><span>Contactpersoon</span><strong><?=$h($customer['contact_name'])?></strong></div><?php } ?>
            <?php if (($customer['email'] ?? '') !== '') { ?><div><span>E-mail</span><strong><?=$h($customer['email'])?></strong></div><?php } ?>
            <div><span>Factuuradres</span><strong><?=implode('<br>', array_map($h, $addressLines))?></strong></div>
        </div>
    </section>

    <section class="panel admin-panel admin-invoice-lines-panel" style="--cw:9;--cw-sm:12;--cw-xs:12">
        <div class="panel__header admin-panel__header"><h3><i class="fas fa-table-cells"></i> Factuurregels</h3></div>
        <div class="panel__body admin-panel__body admin-panel__body--flush admin-invoice-lines-table-wrap">
            <table class="admin-invoice-lines-table">
                <thead><tr><th>Omschrijving</th><th>Aantal</th><th>Eenheid</th><th>Prijs excl.</th><th>Btw</th><th>Netto</th><th>Totaal</th></tr></thead>
                <tbody>
                <?php foreach ($lines as $line) { ?>
                    <tr>
                        <td><?=$h($line['description'])?></td>
                        <td><?=$h($line['quantity'])?></td>
                        <td><?=$h($line['unit'])?></td>
                        <td><?=$h($line['unit_price'])?></td>
                        <td><?=$h($line['tax_label'])?></td>
                        <td><?=$h($line['net'])?></td>
                        <td><strong><?=$h($line['gross'])?></strong></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
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

    <?php if ($invoice->getNotes() !== '') { ?>
        <section class="panel admin-panel admin-invoice-notes-panel" style="--cw:12">
            <div class="panel__header admin-panel__header"><h3><i class="fas fa-note-sticky"></i> Opmerking</h3></div>
            <div class="panel__body admin-panel__body admin-invoice-detail-notes"><?=nl2br($h($invoice->getNotes()))?></div>
        </section>
    <?php } ?>
</grid>
