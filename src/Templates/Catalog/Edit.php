<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$editing = is_array($current ?? null);
$groupMode = ($currentType ?? 'item') === 'group';
$selectedTax = ($current['tax_rate'] ?? '21') . '|' . ($current['tax_category'] ?? 'standard');
$initialVariants = ($groupVariants ?? [])[$selectedParentId ?? ''] ?? [];
$hasParent = !empty($selectedParentId);
$title = $editing ? ($groupMode ? 'Keuzegroep bewerken' : 'Factuuritem bewerken')
    : ($groupMode ? 'Nieuwe keuzegroep' : 'Nieuw factuuritem');
?>
<section class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow">Facturatie</p>
            <h1><?=$h($title)?></h1>
            <p class="admin-page-header__intro">Wijzigingen gelden voor nieuwe factuurconcepten. Bestaande facturen houden hun oorspronkelijke regels en bedragen.</p>
        </div>
    </div>
    <grid>
        <?php if ($groupMode && $editing) { ?>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3>Varianten van deze groep</h3></div>
            <div class="panel__body">
                <p>De groep kiest bij factureren automatisch een van deze items. De gekozen variant levert de prijs en btw.</p>
                <?=new \Flexgrid\Html\Table\TableRenderer($variantTable)?>
                <p><a class="button button-secondary" href="/Flexgrid/AdminInvoiceCatalog/items?parent_id=<?=$h(rawurlencode($current['public_id']))?>">Nieuwe variant toevoegen</a></p>
            </div>
        </div>
        <?php } ?>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3><?=$h($title)?></h3></div>
            <div class="panel__body">
                <?php if ($groupMode) { ?><p>De groep heeft zelf geen prijs. Kies een projectfunctie die bij een nieuwe factuur de juiste variant aanwijst.</p><?php }
                else { ?><p>De naam is voor het beheer. De omschrijving komt op de factuur; <code>{periode}</code> wordt vervangen door de maand en het jaar.</p><?php } ?>
                <form class="admin-form" ajax="true" action="<?=$h($saveAction)?>" method="post">
                    <input type="hidden" name="public_id" value="<?=$h($current['public_id'] ?? '')?>">
                    <input type="hidden" name="item_type" value="<?=$groupMode ? 'group' : 'item'?>">
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Naam voor beheer *</span><input name="name" value="<?=$h($current['name'] ?? '')?>" maxlength="255" required placeholder="<?=$groupMode ? 'Bijv. Basic' : 'Bijv. Basic 1–2'?>"></label>
                    <?php if ($groupMode) { ?>
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Automatische keuzefunctie *</span><select name="choose_function" required>
                        <option value="">Kies een functie...</option>
                        <?php foreach (($choiceFunctions ?? []) as $key => $choice) { ?><option value="<?=$h($key)?>"<?=($current['choose_function'] ?? '') === $key ? ' selected' : ''?>><?=$h($choice['label'])?></option><?php } ?>
                    </select><small>Alleen functies die door het project zijn geregistreerd, zijn beschikbaar.</small></label>
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Valuta voor varianten *</span><input name="currency" value="<?=$h($current['currency'] ?? 'EUR')?>" maxlength="3" required><small>Alle varianten van deze groep gebruiken dezelfde valuta.</small></label>
                    <?php } else { ?>
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Omschrijving op factuur *</span><input name="description" value="<?=$h($current['description'] ?? '')?>" maxlength="500" required placeholder="Bijv. Hosting {periode}"></label>
                    <label class="admin-field" style="--cw:3;--cw-sm:6;--cw-xs:12"><span>Aantal *</span><input name="quantity" value="<?=$h($current['quantity'] ?? '1')?>" required inputmode="decimal"></label>
                    <label class="admin-field" style="--cw:3;--cw-sm:6;--cw-xs:12"><span>Eenheid *</span><input name="unit" value="<?=$h($current['unit'] ?? 'maand')?>" maxlength="32" required></label>
                    <label class="admin-field" style="--cw:3;--cw-sm:6;--cw-xs:12"><span>Prijs per eenheid, excl. btw *</span><input name="unit_price" value="<?=$h($current['unit_price'] ?? '0,00')?>" required inputmode="decimal"></label>
                    <label class="admin-field" style="--cw:3;--cw-sm:6;--cw-xs:12"><span>Valuta *</span><input name="currency" value="<?=$h($current['currency'] ?? 'EUR')?>" maxlength="3" required></label>
                    <label class="admin-field" style="--cw:3;--cw-sm:6;--cw-xs:12"><span>Btw *</span><select name="tax_choice">
                        <?php foreach (['21|standard'=>'21%', '9|reduced'=>'9%', '0|zero'=>'0%', '0|reverse_charge'=>'Btw verlegd', '0|exempt'=>'Vrijgesteld'] as $value => $label) { ?>
                            <option value="<?=$h($value)?>"<?=$selectedTax === $value ? ' selected' : ''?>><?=$h($label)?></option>
                        <?php } ?>
                    </select></label>
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Onderdeel van keuzegroep</span><select name="parent_public_id" id="invoice-item-parent">
                        <option value="">Geen groep · zelfstandig item</option>
                        <?php foreach (($groups ?? []) as $group) { ?><option value="<?=$h($group['public_id'])?>"<?=($selectedParentId ?? '') === $group['public_id'] ? ' selected' : ''?>><?=$h($group['name'])?></option><?php } ?>
                    </select><small>Open de groep om nieuwe varianten met de juiste sleutel aan te maken.</small></label>
                    <label class="admin-field" style="--cw:6;--cw-sm:12"><span>Variant binnen de groep</span>
                        <select name="variant_key" id="invoice-item-variant-select"<?=!$hasParent || $initialVariants === [] ? ' disabled hidden' : ' required'?>><option value="">Kies de variant...</option><?php foreach ($initialVariants as $key => $label) { ?><option value="<?=$h($key)?>"<?=($current['variant_key'] ?? '') === $key ? ' selected' : ''?>><?=$h($label)?></option><?php } ?></select>
                        <input name="variant_key" id="invoice-item-variant-input" value="<?=$h($current['variant_key'] ?? '')?>" maxlength="64" placeholder="Variantsleutel"<?=$hasParent && $initialVariants === [] ? ' required' : ' disabled hidden'?>>
                        <small id="invoice-item-variant-help">Kies eerst een groep. De toegestane varianten verschijnen hier automatisch.</small></label>
                    <?php } ?>
                    <label class="admin-field" style="--cw:12"><span>Beschikbaar voor nieuwe facturen</span><input type="checkbox" name="active" value="1"<?=!$editing || (int)$current['active'] === 1 ? ' checked' : ''?>></label>
                    <div style="--cw:12"><button class="button button-publish" type="submit"><?=$groupMode ? 'Groep opslaan' : 'Item opslaan'?></button></div>
                </form>
                <?php if ($editing) { ?>
                    <?php if ($deleteReason === null) { ?><p><button class="button button-delete" type="button" ajax="true" action="<?=$h($deleteAction ?? '')?>" public_id="<?=$h($current['public_id'])?>" use-waiting-icon alert="Dit factuuritem definitief verwijderen? Dit kan niet ongedaan worden gemaakt.">Item definitief verwijderen</button></p><?php }
                    else { ?><p class="admin-field-help">Verwijderen is nu niet mogelijk: <?=$h($deleteReason)?> Je kunt het item wel pauzeren door 'Beschikbaar voor nieuwe facturen' uit te zetten.</p><?php } ?>
                <?php } ?>
            </div>
        </div>
    </grid>
</section>
<?php if (!$groupMode) { ?>
<script>
(function () {
    var parent = document.getElementById('invoice-item-parent');
    if (!parent) return;
    var select = document.getElementById('invoice-item-variant-select');
    var input = document.getElementById('invoice-item-variant-input');
    var help = document.getElementById('invoice-item-variant-help');
    var variants = <?=json_encode($groupVariants ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
    var original = <?=json_encode((string)($current['variant_key'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
    function refresh(initial) {
        var group = parent.value;
        var options = variants[group] || {};
        var keys = Object.keys(options);
        var listed = group !== '' && keys.length > 0;
        select.hidden = !listed;
        select.disabled = !listed;
        select.required = listed;
        input.hidden = listed || group === '';
        input.disabled = listed || group === '';
        input.required = group !== '' && !listed;
        select.innerHTML = '';
        if (listed) {
            select.add(new Option('Kies de variant...', ''));
            keys.forEach(function (key) { select.add(new Option(options[key], key)); });
            select.value = initial ? original : '';
            help.textContent = 'Kies de prijsvariant die dit item vertegenwoordigt.';
        } else {
            input.value = initial ? original : '';
            help.textContent = group === '' ? 'Zonder groep is dit een zelfstandig factuuritem.'
                : 'Deze functie heeft geen vaste varianten; geef zelf een unieke sleutel op.';
        }
    }
    parent.addEventListener('change', function () { refresh(false); });
    refresh(true);
}());
</script>
<?php } ?>
