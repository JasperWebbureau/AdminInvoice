<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$primary = $brand['primary'] ?? '#1F2A44';
$secondary = $brand['secondary'] ?? '#4758BE';
$isCreditNote = ($invoice['document_type'] ?? 'invoice') === 'credit_note';
$isPreview = !empty($invoice['is_preview']);
$documentTitle = $invoice['title'] ?? ($isCreditNote ? 'Creditnota' : 'Factuur');
$customer = $invoice['customer'] ?? [];
$address = $invoice['address'] ?? [];
$customerAddress = array_filter([
    $address['line_1'] ?? '',
    $address['line_2'] ?? '',
    trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')),
    $address['region'] ?? '',
    $address['country_code'] ?? '',
], static function ($value): bool {
    return trim((string)$value) !== '';
});
$companyAddress = array_filter([
    $company['street'] ?? '',
    trim(($company['postal'] ?? '') . ' ' . ($company['city'] ?? '')),
    $company['country'] ?? '',
], static function ($value): bool {
    return trim((string)$value) !== '';
});
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202633; font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; line-height: 1.45; }
        .brand-line { height: 9px; background: <?=$h($primary)?>; border-bottom: 3px solid <?=$h($secondary)?>; }
        .page { padding: 34px 42px 70px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo-cell { width: 52%; }
        .logo { max-width: 190px; max-height: 72px; }
        .company { text-align: right; color: #555f70; }
        .company-name { margin-bottom: 5px; color: <?=$h($primary)?>; font-size: 14pt; font-weight: bold; }
        .title-row { margin: 34px 0 25px; }
        .title { margin: 0; color: <?=$h($primary)?>; font-size: 27pt; letter-spacing: .4px; }
        .invoice-number { margin-top: 4px; color: <?=$h($secondary)?>; font-size: 12pt; font-weight: bold; }
        .preview-notice { margin: -12px 0 24px; padding: 12px 16px; color: #743800; background: #fff4dd; border: 2px solid #d4882b; font-weight: bold; }
        .meta { margin: 0 0 25px; }
        .meta > tbody > tr > td { width: 50%; padding: 16px 18px; vertical-align: top; background: #f5f6f8; }
        .meta > tbody > tr > td:first-child { border-left: 4px solid <?=$h($primary)?>; }
        .meta > tbody > tr > td:last-child { border-right: 4px solid <?=$h($secondary)?>; }
        .section-label { margin-bottom: 7px; color: <?=$h($secondary)?>; font-size: 8pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .customer-name { margin-bottom: 4px; color: <?=$h($primary)?>; font-size: 12pt; font-weight: bold; }
        .dates td { padding: 2px 0; background: transparent; border: 0; }
        .dates td:first-child { color: #677080; }
        .dates td:last-child { text-align: right; font-weight: bold; }
        .lines { margin-bottom: 18px; }
        .lines thead { display: table-header-group; }
        .lines th { padding: 9px 8px; color: #fff; background: <?=$h($primary)?>; border-bottom: 3px solid <?=$h($secondary)?>; font-size: 8pt; text-align: left; }
        .lines th.number, .lines td.number { text-align: right; white-space: nowrap; }
        .lines td { padding: 10px 8px; border-bottom: 1px solid #e1e4e9; vertical-align: top; }
        .lines tbody tr:nth-child(even) td { background: #fafbfc; }
        .line-description { color: <?=$h($primary)?>; font-weight: bold; }
        .totals-wrap td { vertical-align: top; }
        .payment { width: 56%; padding: 13px 16px; color: #555f70; background: #f7f8fa; border-left: 3px solid <?=$h($secondary)?>; }
        .payment-qr { width: 96px; height: 96px; margin-top: 10px; }
        .payment-link { margin-top: 8px; overflow-wrap: break-word; font-size: 7.5pt; }
        .totals-spacer { width: 5%; }
        .totals { width: 39%; }
        .totals td { padding: 5px 0 5px 14px; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .grand td { padding-top: 10px; color: <?=$h($primary)?>; border-top: 2px solid <?=$h($secondary)?>; font-size: 13pt; font-weight: bold; }
        .note { margin-top: 22px; padding: 12px 15px; color: #555f70; border: 1px solid #e1e4e9; }
        .reverse-charge { margin-top: 14px; color: #555f70; font-size: 8.5pt; }
        .nowrap { white-space: nowrap; }
        .footer { position: fixed; right: 42px; bottom: 24px; left: 42px; padding-top: 8px; color: #7b8493; border-top: 1px solid #dfe2e7; font-size: 7.5pt; text-align: center; }
        .footer span { margin: 0 8px; }
    </style>
</head>
<body>
<div class="brand-line"></div>
<main class="page">
    <table class="header">
        <tr>
            <td class="logo-cell">
                <?php if (($company['logo_data_uri'] ?? '') !== '') { ?>
                    <img class="logo" src="<?=$h($company['logo_data_uri'])?>" alt="<?=$h($company['name'] ?? '')?>">
                <?php } else { ?>
                    <div class="company-name" style="text-align:left"><?=$h($company['name'] ?? '')?></div>
                <?php } ?>
            </td>
            <td class="company">
                <div class="company-name"><?=$h($company['name'] ?? '')?></div>
                <?php foreach ($companyAddress as $line) { ?><div><?=$h($line)?></div><?php } ?>
                <?php if (($company['email'] ?? '') !== '') { ?><div><?=$h($company['email'])?></div><?php } ?>
                <?php if (($company['tel'] ?? '') !== '') { ?><div><?=$h($company['tel'])?></div><?php } ?>
            </td>
        </tr>
    </table>

    <div class="title-row">
        <h1 class="title"><?=$h($documentTitle)?></h1>
        <div class="invoice-number"><?=$isPreview ? 'Nog geen factuurnummer' : $h($invoice['number'] ?? '')?></div>
    </div>
    <?php if ($isPreview) { ?>
        <div class="preview-notice">CONCEPT &mdash; Alleen ter controle. Dit is geen definitieve factuur. Niet betalen.</div>
    <?php } ?>

    <table class="meta">
        <tr>
            <td>
                <div class="section-label"><?=$isCreditNote ? 'Creditnota voor' : 'Factuur voor'?></div>
                <div class="customer-name"><?=$h($customer['name'] ?? '')?></div>
                <?php if (($customer['contact_name'] ?? '') !== '') { ?><div>T.a.v. <?=$h($customer['contact_name'])?></div><?php } ?>
                <?php foreach ($customerAddress as $line) { ?><div><?=$h($line)?></div><?php } ?>
            </td>
            <td>
                <div class="section-label"><?=$isCreditNote ? 'Creditnotagegevens' : 'Factuurgegevens'?></div>
                <table class="dates">
                    <tr><td><?=$isCreditNote ? 'Creditdatum' : 'Factuurdatum'?></td><td><?=$h($invoice['issue_date'] ?? '')?></td></tr>
                    <?php if (!$isCreditNote) { ?><tr><td>Vervaldatum</td><td><?=$h($invoice['due_date'] ?? '')?></td></tr><?php } ?>
                    <?php if ($isCreditNote && ($invoice['original_invoice_number'] ?? '') !== '') { ?>
                        <tr><td>Bronfactuur</td><td><?=$h($invoice['original_invoice_number'])?></td></tr>
                    <?php } ?>
                    <?php if (($invoice['customer_reference'] ?? '') !== '') { ?>
                        <tr><td>Referentie</td><td><?=$h($invoice['customer_reference'])?></td></tr>
                    <?php } ?>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th>Omschrijving</th><th class="number">Aantal</th><th>Eenheid</th><th class="number">Prijs excl.</th><th class="number">Btw</th><th class="number">Totaal excl.</th></tr></thead>
        <tbody>
        <?php foreach (($invoice['lines'] ?? []) as $line) { ?>
            <tr>
                <td class="line-description"><?=$h($line['description'] ?? '')?></td>
                <td class="number"><?=$h($line['quantity'] ?? '')?></td>
                <td><?=$h($line['unit'] ?? '')?></td>
                <td class="number"><?=$h($line['unit_price'] ?? '')?></td>
                <td class="number"><?=$h($line['tax_label'] ?? '')?></td>
                <td class="number"><?=$h($line['net'] ?? '')?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td class="payment">
                <?php if ($isPreview) { ?>
                    <div class="section-label">Concept</div>
                    Deze PDF is alleen bedoeld om de factuur te controleren. Betaalgegevens verschijnen pas op de definitieve factuur.
                <?php } elseif ($isCreditNote) { ?>
                    <div class="section-label">Correctie</div>
                    Deze creditnota corrigeert factuur <strong><?=$h($invoice['original_invoice_number'] ?? '')?></strong> volledig.
                <?php } else { ?>
                    <div class="section-label">Betaling</div>
                    <?php if (($invoice['collection_notice'] ?? '') !== '') { ?>
                    <?=$h($invoice['collection_notice'])?>
                    <?php } else { ?>
                    Gelieve het totaal uiterlijk op <strong><?=$h($invoice['due_date'] ?? '')?></strong>
                    <?php if (($company['iban'] ?? '') !== '') { ?>over te maken naar <strong><?=$h($company['iban'])?></strong><?php } ?>
                    <?php if (($company['iban_holder'] ?? '') !== '') { ?> t.n.v. <?=$h($company['iban_holder'])?><?php } ?>
                    onder vermelding van <span class="nowrap"><?=$h($invoice['number'] ?? '')?></span>.
                    <?php if (($invoice['payment_url'] ?? '') !== '') { ?>
                        <div class="payment-link">Of betaal online: <a href="<?=$h($invoice['payment_url'])?>"><?=$h($invoice['payment_url'])?></a></div>
                        <?php if (($invoice['payment_qr_data_uri'] ?? '') !== '') { ?><img class="payment-qr" src="<?=$h($invoice['payment_qr_data_uri'])?>" alt="QR-code voor online betaling"><?php } ?>
                    <?php } ?>
                    <?php } ?>
                <?php } ?>
            </td>
            <td class="totals-spacer"></td>
            <td class="totals">
                <table>
                    <tr><td>Subtotaal</td><td><?=$h($invoice['net_total'] ?? '')?></td></tr>
                    <?php foreach (($invoice['tax_rows'] ?? []) as $row) { ?>
                        <tr><td><?=$h($row['label'] ?? '')?></td><td><?=$h($row['tax'] ?? '')?></td></tr>
                    <?php } ?>
                    <tr class="grand"><td>Totaal</td><td><?=$h($invoice['gross_total'] ?? '')?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <?php if (!empty($invoice['has_reverse_charge'])) { ?>
        <div class="reverse-charge">De btw is verlegd naar de afnemer.</div>
    <?php } ?>
    <?php if ($isCreditNote && ($invoice['reason'] ?? '') !== '') { ?>
        <div class="note"><div class="section-label">Reden</div><?=nl2br($h($invoice['reason']))?></div>
    <?php } elseif (($invoice['notes'] ?? '') !== '') { ?>
        <div class="note"><div class="section-label">Opmerking</div><?=nl2br($h($invoice['notes']))?></div>
    <?php } ?>
</main>
<div class="footer">
    <?php if ($isPreview) { ?><span><strong>CONCEPT · NIET BETALEN</strong></span><?php } ?>
    <?php if (($company['kvk'] ?? '') !== '') { ?><span>KvK <?=$h($company['kvk'])?></span><?php } ?>
    <?php if (($company['vat_nr'] ?? '') !== '') { ?><span>Btw <?=$h($company['vat_nr'])?></span><?php } ?>
    <?php if (!$isPreview && ($company['iban'] ?? '') !== '') { ?><span>IBAN <?=$h($company['iban'])?></span><?php } ?>
    <?php if (($company['domain'] ?? '') !== '') { ?><span><?=$h($company['domain'])?></span><?php } ?>
</div>
</body>
</html>
