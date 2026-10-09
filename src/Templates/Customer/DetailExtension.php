<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="panel admin-panel admin-customer-invoice-extension" style="--cw:12" data-admin-customer-invoice-extension>
    <div class="panel__header admin-panel__header admin-customer-section-header">
        <h3><i class="fas fa-file-invoice"></i> Facturatie</h3>
        <?php if (!empty($canCreateInvoice)) { ?>
            <a class="button button-publish" href="<?=$h($createInvoiceUrl ?? '')?>"><i class="fas fa-file-circle-plus"></i> Nieuwe factuur</a>
        <?php } ?>
    </div>
    <div class="panel__body admin-panel__body">
        <?php if (!empty($canCreateInvoice)) { ?>
            <p>Documenten van <?=$h($customerName ?? '')?>. Een nieuw concept gebruikt de actuele klant- en factuuradresgegevens.</p>
        <?php } elseif (empty($isActive)) { ?>
            <p class="admin-field-help">Activeer deze klant voordat je een nieuwe factuur aanmaakt.</p>
        <?php } ?>

        <?php
        $historyRenderer = new \Flexgrid\Html\Table\TableRenderer($historyTable ?? []);
        echo $historyRenderer->render();
        ?>
    </div>
</section>
<section class="panel admin-panel admin-customer-recurring-extension" style="--cw:12">
    <div class="panel__header admin-panel__header admin-customer-section-header">
        <h3><i class="fas fa-repeat"></i> Terugkerende facturatie</h3>
        <a class="button button-secondary" href="/Flexgrid/AdminRecurringInvoice/plans">Schema's beheren</a>
    </div>
    <div class="panel__body admin-panel__body">
        <p>Een schema bepaalt <strong>wanneer</strong> er wordt gefactureerd. De items eronder bepalen de vaste regels; een aangesloten project kan ook automatisch extra regels toevoegen. Alle regels binnen hetzelfde schema worden één conceptfactuur. Controleer dit met de proefrun. Er wordt niets automatisch verstuurd of betaald.</p>
        <?php if (empty($canCreateInvoice)) { ?>
            <p class="admin-field-help">Deze klant moet actief zijn voordat je een schema kunt koppelen.</p>
        <?php } elseif (!empty($availablePlans)) { ?>
            <form class="admin-form" ajax="true" action="<?=$h($enrollAction ?? '')?>" method="post">
                <input type="hidden" name="customer_public_id" value="<?=$h($customerPublicId ?? '')?>">
                <label class="admin-field"><span>Schema toevoegen aan deze klant</span>
                    <select name="plan_public_id" required>
                        <option value="">Kies een herhaalschema...</option>
                        <?php foreach ($availablePlans as $plan) { ?>
                            <option value="<?=$h($plan['public_id'])?>"><?=$h($plan['name'] . ' · eerstvolgende datum ' . $plan['upcoming_date'])?></option>
                        <?php } ?>
                    </select>
                </label>
                <button class="button button-publish" type="submit">Schema koppelen</button>
            </form>
        <?php } else { ?>
            <p>Geen nieuw actief schema beschikbaar. Maak eventueel eerst een <a href="/Flexgrid/AdminRecurringInvoice/plans">herhaalschema</a>.</p>
        <?php } ?>
        <?php if (!empty($memberships)) { ?>
            <h4>Gekoppelde schema's</h4>
            <grid class="fluid">
            <?php foreach ($memberships as $member) { ?>
                <div class="panel admin-panel" style="--cw:12">
                    <div class="panel__header admin-panel__header">
                        <h4><?=$h($member['plan_name'])?></h4>
                        <button class="button button-secondary button-small" type="button" ajax="true" action="<?=$h($toggleMembershipAction ?? '')?>" plan_public_id="<?=$h($member['plan_public_id'])?>" customer_public_id="<?=$h($customerPublicId ?? '')?>" active="<?=(int)$member['active'] === 1 ? '0' : '1'?>" use-waiting-icon><?=(int)$member['active'] === 1 ? 'Schema voor klant pauzeren' : 'Schema voor klant hervatten'?></button>
                    </div>
                    <div class="panel__body admin-panel__body">
                        <p>Volgende conceptdatum: <strong><?=$h($member['next_issue_date'])?></strong> · <?=(int)$member['active'] === 1 ? ((int)$member['plan_active'] === 1 ? 'Actief' : 'Schema centraal gepauzeerd') : 'Voor deze klant gepauzeerd'?>.</p>
                        <?php if (trim((string)($member['last_error'] ?? '')) !== '') { ?><p class="admin-field-help">Laatste fout: <?=$h($member['last_error'])?></p><?php } ?>
                        <h5>Factuuritems voor deze klant</h5>
                        <?php if (empty($member['items'])) { ?><p>Geen vaste items toegevoegd. Een aangesloten project kan nog automatisch een regel leveren; zonder zo'n regel wordt geen leeg concept gemaakt. Bekijk de proefrun om dit te controleren.</p><?php } else { ?>
                            <table class="admin-data-table"><thead><tr><th>Item</th><th>Catalogusprijs</th><th>Status</th><th></th></tr></thead><tbody>
                                <?php foreach ($member['items'] as $item) { ?><tr>
                                    <td><?=$h($item['item_name'] ?? 'Item niet beschikbaar')?></td>
                                    <td><?=(string)($item['item_type'] ?? '') === 'group' ? 'Automatische keuze per factuurrun' : $h(($item['currency'] ?? '') . ' ' . ($item['unit_price'] ?? '')) . ' per ' . $h($item['quantity'] ?? '1') . ' ' . $h($item['unit'] ?? 'stuk')?></td>
                                    <td><?=(int)$item['active'] === 1 ? 'Actief' : 'Gepauzeerd'?></td>
                                    <td><button class="button button-secondary button-small" type="button" ajax="true" action="<?=$h($toggleItemAction ?? '')?>" plan_public_id="<?=$h($member['plan_public_id'])?>" customer_public_id="<?=$h($customerPublicId ?? '')?>" catalog_item_public_id="<?=$h($item['catalog_item_public_id'])?>" active="<?=(int)$item['active'] === 1 ? '0' : '1'?>" use-waiting-icon><?=(int)$item['active'] === 1 ? 'Item pauzeren' : 'Item hervatten'?></button></td>
                                </tr><?php } ?>
                            </tbody></table>
                            <p class="admin-field-help">Een keuzegroep kiest bij elke run opnieuw de passende variant. Gewone items gebruiken hun actuele catalogusprijs.</p>
                        <?php } ?>
                        <?php if (!empty($canCreateInvoice) && !empty($member['selectable_items'])) { ?>
                            <form class="admin-form" ajax="true" action="<?=$h($addItemAction ?? '')?>" method="post">
                                <input type="hidden" name="customer_public_id" value="<?=$h($customerPublicId ?? '')?>">
                                <input type="hidden" name="plan_public_id" value="<?=$h($member['plan_public_id'])?>">
                                <label class="admin-field"><span>Factuuritem toevoegen</span><select name="catalog_item_public_id" required>
                                    <option value="">Kies een item...</option>
                                    <?php foreach ($member['selectable_items'] as $catalogItem) { ?><option value="<?=$h($catalogItem['public_id'])?>"><?=$h($catalogItem['name'] . ' · ' . $catalogItem['currency'] . ' ' . $catalogItem['unit_price'])?></option><?php } ?>
                                </select></label>
                                <button class="button button-publish" type="submit">Item toevoegen</button>
                            </form>
                        <?php } elseif (empty($catalogItems)) { ?><p>Maak eerst een <a href="/Flexgrid/AdminInvoiceCatalog/items">factuuritem</a> aan.</p><?php } elseif (!empty($canCreateInvoice)) { ?><p>Er zijn geen andere passende items beschikbaar. Je kunt een nieuw <a href="/Flexgrid/AdminInvoiceCatalog/items">factuuritem</a> maken.</p><?php } ?>
                    </div>
                </div>
            <?php } ?>
            </grid>
            <p class="admin-field-help">Pauzeren en hervatten verandert bestaande facturen niet. Gemiste perioden worden niet achteraf gefactureerd.</p>
        <?php } ?>
    </div>
</section>
