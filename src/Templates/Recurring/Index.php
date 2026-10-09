<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$editing = is_array($current ?? null);
?>
<section class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow">Facturatie</p>
            <h1><?=$showEditor ? ($editing ? 'Herhaalschema bewerken' : 'Nieuw herhaalschema') : 'Terugkerende facturen'?></h1>
            <p class="admin-page-header__intro">Een schema bepaalt wanneer conceptfacturen worden gemaakt. Na opslaan kun je hier klanten koppelen; vaste factuuritems stel je per klant in. Een projectkoppeling kan ook automatisch regels toevoegen. Er wordt niets verstuurd of geïncasseerd.</p>
        </div>
    </div>
    <grid>
    <?php if ($showEditor ?? false) { ?>
    <?php if (($customerSelector ?? '') === '') { ?>
        <div class="panel admin-panel" style="--cw:12"><div class="panel__body admin-panel__body"><p>Er is nog geen klantkoppeling beschikbaar. Installeer of activeer een klantprovider voordat je een herhaalschema maakt.</p></div></div>
    <?php } else { ?>
        <div class="panel admin-panel" style="--cw:12">
            <div class="panel__header admin-panel__header"><h3><?=$editing ? 'Herhaalschema bewerken' : 'Nieuw gedeeld herhaalschema'?></h3></div>
            <div class="panel__body admin-panel__body">
                <p>Stel hier de planning in en koppel daarna klanten. Voeg op hun klantpagina vaste factuuritems toe als die nodig zijn. Automatische projectregels komen binnen hetzelfde schema op het concept; zonder enige regel ontstaat geen factuur.</p>
                <?php if (!empty($legacy)) { ?><p class="admin-field-help">Dit bestaande schema is voor één klant. Pauzeer het voordat je hetzelfde item onder een gedeeld schema van die klant toevoegt.</p><?=$customerSelector?><?php } elseif ($editing && (string)($current['catalog_item_public_id'] ?? '') !== '') { ?><p class="admin-field-help">Dit schema heeft nog een oud standaarditem. Bij opslaan wordt dat item eerst overgenomen bij alle bestaande klantkoppelingen. Daarna beheer je items alleen nog per klant.</p><?php } ?>
                <form class="admin-form admin-form-grid" <?php if (!empty($legacy)) { ?>data-admin-invoice-customer-form<?php } ?> ajax="true" action="<?=$h($saveAction)?>" method="post">
                    <input type="hidden" name="public_id" value="<?=$h($current['public_id'] ?? '')?>">
                    <?php if (!empty($legacy)) { ?><input type="hidden" name="customer[source_public_id]" value="<?=$h($selectedCustomerId ?? '')?>"><?php } ?>
                    <label class="admin-field"><span>Naam voor beheer *</span><input name="name" value="<?=$h($current['name'] ?? '')?>" maxlength="255" required placeholder="Bijv. Premium maandelijks of Hosting"></label>
                    <?php if (!empty($legacy)) { ?><label class="admin-field"><span>Factuuritem *</span><select name="catalog_item_public_id" required>
                        <?php foreach ($items as $item) { ?><option value="<?=$h($item['public_id'])?>"<?=($selectedCatalogItemId ?? '') === $item['public_id'] ? ' selected' : ''?>><?=$h($item['name'] . ' · ' . $item['currency'] . ' ' . $item['unit_price'])?></option><?php } ?>
                    </select></label><?php } ?>
                    <label class="admin-field"><span><?=$editing && !empty($legacy) ? 'Volgende factuurdatum' : 'Eerste factuurdatum'?> *</span><input type="date" name="next_issue_date" value="<?=$h($current['next_issue_date'] ?? date('Y-m-d'))?>" required><small>Een later gekoppelde klant start op de eerstvolgende datum; eerdere maanden worden niet ingehaald. Als je de datum of herhaling wijzigt, verschuift die ook voor actieve klantkoppelingen.</small></label>
                    <label class="admin-field"><span>Herhalen *</span><select name="frequency_months">
                        <?php foreach ([1=>'Elke maand',3=>'Elk kwartaal',12=>'Elk jaar'] as $months=>$label) { ?><option value="<?=$months?>"<?=((int)($current['frequency_months'] ?? 1) === $months) ? ' selected' : ''?>><?=$h($label)?></option><?php } ?>
                    </select></label>
                    <label class="admin-field"><span>Betaaltermijn in dagen *</span><input type="number" name="due_days" min="0" max="365" value="<?=$h($current['due_days'] ?? 14)?>" required><small>De vervaldatum op ieder nieuw concept.</small></label>
                    <label class="admin-field"><span>Schema actief</span><input type="checkbox" name="active" value="1"<?=!$editing || (int)$current['active'] === 1 ? ' checked' : ''?>><small>Zet uit om nieuwe concepten tijdelijk te stoppen.</small></label>
                    <div><button class="button button-publish" type="submit">Schema opslaan</button> <?php if ($editing) { ?><a class="button button-secondary" href="/Flexgrid/AdminRecurringInvoice/create">Nieuw schema</a><?php } ?></div>
                </form>
            </div>
        </div>
        <?php if ($editing && empty($legacy)) { ?>
        <div class="panel admin-panel" style="--cw:12">
            <div class="panel__header admin-panel__header"><h3>Gekoppelde klanten (<?=count($linkedCustomers ?? [])?>)</h3></div>
            <div class="panel__body admin-panel__body">
                <?=(new \Flexgrid\Html\Table\TableRenderer($linkedCustomerTable))->render()?>
            </div>
        </div>
        <div class="panel admin-panel admin-invoice-customer-selector" style="--cw:12">
            <div class="panel__header admin-panel__header"><h3>Klant aan dit schema koppelen</h3></div>
            <div class="panel__body admin-panel__body">
                <?php if ((int)$current['active'] !== 1) { ?><p>Dit schema is gepauzeerd. Activeer het schema hierboven en sla het op voordat je klanten koppelt.</p><?php } else { ?>
                <p>Zoek een actieve klant. Factuuradres en contactpersoon zijn optioneel. Na het koppelen open je de klant met het potloodicoon om de factuuritems voor dit schema toe te voegen.</p>
                <form class="admin-form admin-invoice-customer-search" data-admin-invoice-customer-search ajax="true" action="<?=$h($customerSearchAction ?? '')?>" method="post">
                    <label class="admin-data-search"><span class="admin-visually-hidden">Zoek een klant</span><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="q" placeholder="Zoek op klant, contact, e-mail, adres of nummer…" autocomplete="off"></label>
                    <button class="button button-secondary" type="submit">Zoeken</button>
                </form>
                <div class="admin-invoice-customer-selected" data-admin-invoice-customer-selected data-selected-prefix="Geselecteerd:" data-empty-label="Nog geen klant gekozen">
                    <span data-admin-invoice-customer-selected-label>Nog geen klant gekozen</span>
                    <button type="button" class="button button-secondary" data-admin-invoice-customer-clear hidden>Keuze wissen</button>
                </div>
                <div class="admin-invoice-customer-results" data-admin-invoice-customer-results aria-live="polite"></div>
                <form class="admin-form" data-admin-invoice-customer-form ajax="true" action="<?=$h($enrollFromPlanAction ?? '')?>" method="post">
                    <input type="hidden" name="plan_public_id" value="<?=$h($current['public_id'])?>">
                    <input type="hidden" name="customer[source_public_id]" value="">
                    <button class="button button-publish" type="submit">Geselecteerde klant koppelen</button>
                </form>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    <?php } ?>
    <?php } ?>
    <?php if (!($showEditor ?? false)) { ?>
    <div class="panel admin-panel" style="--cw:12">
        <div class="panel__header admin-panel__header"><h3>Geplande facturen</h3></div>
        <div class="panel__body admin-panel__body">
            <p>De dagelijkse taak verwerkt actieve klantkoppelingen waarvan de factuurdatum is bereikt. Bekijk eerst de proefrun: die maakt niets aan en toont ook eventuele fouten.</p>
            <a class="button button-primary" href="<?=$h($previewUrl ?? '')?>"><i class="fas fa-eye" aria-hidden="true"></i> Proefrun bekijken</a>
            <button class="button button-secondary" type="button" ajax="true" action="<?=$h($runAction)?>" alert="Nu alle vervallen herhaalschema's verwerken? Er worden factuurconcepten aangemaakt; controleer ze daarna in Facturen." use-waiting-icon>Nu concepten aanmaken</button>
            <?=(new \Flexgrid\Html\Table\TableRenderer($planTable))->render()?>
        </div>
    </div>
    <?php } ?>
    </grid>
</section>
