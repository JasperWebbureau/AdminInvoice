<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<div class="panel" style="--cw:12" data-admin-invoice-dispatch-panel>
    <div class="panel__header"><h3>Verzenden en herinneren</h3></div>
    <div class="panel__body">
        <p>Definitief maken verstuurt niets. Verstuur de PDF pas na controle naar het e-mailadres hieronder. Bij een geconfigureerde live Mollie-koppeling staat de betaallink met QR-code op de PDF. Een herinnering is pas mogelijk als de vervaldatum is verstreken en de factuur nog openstaat.</p>
        <?php if (($mailCatchAll ?? '') !== '') { ?><p><strong>Testpostvak actief:</strong> uitgaande e-mail gaat naar <?=$h($mailCatchAll)?>, niet naar het ingevulde klantadres. De verzendhistorie bewaart wel het bedoelde klantadres.</p><?php } ?>
        <?php if (!empty($history)) { ?>
            <table class="admin-data-table"><thead><tr><th>Actie</th><th>Ontvanger</th><th>Status</th><th>Datum</th></tr></thead><tbody>
            <?php foreach ($history as $entry) { ?>
                <tr><td><?=$entry['kind'] === 'reminder' ? 'Herinnering' : 'Factuur'?><?=$entry['attempt'] > 1 ? ' (poging ' . $h($entry['attempt']) . ')' : ''?></td><td><?=$h($entry['recipient'])?></td><td><?=$h(['sent' => 'Verzonden', 'pending' => 'Wordt verwerkt / uitkomst onbekend', 'failed' => 'Mislukt — controle nodig'][$entry['status']] ?? $entry['status'])?></td><td><?=$h(date('d-m-Y H:i', (int)($entry['sent_at'] ?: $entry['created_at'])))?></td></tr>
            <?php } ?></tbody></table>
        <?php } ?>
        <?php if ($canSend ?? false) { ?>
            <form class="admin-form" ajax="true" action="<?=$h($dispatchAction ?? '')?>" method="post"<?php if (empty($invoiceRepeat)) { ?> onsubmit="return window.confirm('Definitieve factuur met PDF naar dit e-mailadres sturen?')"<?php } ?> use-waiting-icon>
                <input type="hidden" name="public_id" value="<?=$h($publicId ?? '')?>">
                <input type="hidden" name="kind" value="invoice">
                <label class="admin-field" style="--cw:8;--cw-sm:12"><span>E-mailadres klant</span><input type="email" name="recipient" value="<?=$h($recipient ?? '')?>" required autocomplete="email"><small>Controleer dit adres voordat je de definitieve factuur verstuurt.</small></label>
                <?php if ($invoiceRepeat ?? false) { ?>
                    <label class="admin-invoice-repeat-confirm" style="--cw:12"><input type="checkbox" name="confirm_repeat" value="1" required><span>Ik heb de eerdere poging gecontroleerd en begrijp dat de klant deze factuur mogelijk twee keer ontvangt.</span></label>
                <?php } ?>
                <div style="--cw:12"><button class="button button-publish" type="submit"><?=!empty($invoiceRepeat) ? 'Factuur opnieuw mailen' : 'Factuur mailen'?></button></div>
            </form>
        <?php } ?>
        <?php if ($canRemind ?? false) { ?>
            <form class="admin-form" ajax="true" action="<?=$h($dispatchAction ?? '')?>" method="post"<?php if (empty($reminderRepeat)) { ?> onsubmit="return window.confirm('Betalingsherinnering met PDF naar dit e-mailadres sturen?')"<?php } ?> use-waiting-icon>
                <input type="hidden" name="public_id" value="<?=$h($publicId ?? '')?>">
                <input type="hidden" name="kind" value="reminder">
                <label class="admin-field" style="--cw:8;--cw-sm:12"><span>E-mailadres klant</span><input type="email" name="recipient" value="<?=$h($recipient ?? '')?>" required autocomplete="email"><small>Een herinnering is alleen mogelijk als de factuur vervallen en nog niet volledig betaald is.</small></label>
                <?php if ($reminderRepeat ?? false) { ?>
                    <label class="admin-invoice-repeat-confirm" style="--cw:12"><input type="checkbox" name="confirm_repeat" value="1" required><span>Ik heb de eerdere poging gecontroleerd en begrijp dat de klant deze herinnering mogelijk twee keer ontvangt.</span></label>
                <?php } ?>
                <div style="--cw:12"><button class="button button-secondary" type="submit"><?=!empty($reminderRepeat) ? 'Herinnering opnieuw mailen' : 'Herinnering mailen'?></button></div>
            </form>
        <?php } ?>
        <?php if ($mailInProgress ?? false) { ?>
            <p>Een recente verzendpoging wordt mogelijk nog verwerkt. Controleer de mailprovider; na 15 minuten kun je zo nodig bewust opnieuw mailen.</p>
        <?php } ?>
    </div>
</div>
