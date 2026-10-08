<?php
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$counts = $report['counts'] ?? ['ready' => 0, 'skipped' => 0, 'failed' => 0];
?>
<section class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow">Terugkerende facturen</p>
            <h1>Proefrun voor <?=$h($report['date'] ?? '')?></h1>
            <p class="admin-page-header__intro">Dit is een momentopname. Er is niets opgeslagen of verzonden. De echte run kan afwijken als klanten, auto's, items of prijzen intussen veranderen.</p>
        </div>
    </div>
    <grid>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3>Uitkomst van de proefrun</h3></div>
            <div class="panel__body">
                <p><strong><?=$h($counts['ready'] ?? 0)?></strong> concepten verwacht · <strong><?=$h($counts['skipped'] ?? 0)?></strong> perioden zonder regels · <strong><?=$h($counts['failed'] ?? 0)?></strong> fouten</p>
                <?=(new \Flexgrid\Html\Table\TableRenderer($previewTable))->render()?>
                <p>Een echte run maakt alleen bewerkbare conceptfacturen. Daarna controleer je deze voordat je ze definitief maakt.</p>
                <button class="button button-publish" type="button" ajax="true" action="<?=$h($runAction ?? '')?>" alert="De proefrun slaat niets op. Nu de echte run starten en conceptfacturen aanmaken?" use-waiting-icon<?=($counts['ready'] ?? 0) < 1 ? ' disabled' : ''?>>Nu concepten aanmaken</button>
            </div>
        </div>
    </grid>
</section>
