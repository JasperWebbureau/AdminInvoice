<?php $h = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }; ?>
<section class="admin-page">
    <div class="admin-page-header"><div><h1>Factuurverwerking</h1><p>Herhaalschema’s maken facturen klaar. Bepaal hier of je ze eerst zelf controleert.</p></div></div>
    <grid>
        <div class="panel" style="--cw:12"><div class="panel__header"><h3>Facturen in verwerking</h3></div><div class="panel__body"><?=new \Flexgrid\Html\Table\TableRenderer($table)?></div></div>
        <div class="panel" style="--cw:6;--cw-sm:12">
            <div class="panel__header"><h3>Controle van maandfacturen</h3></div>
            <div class="panel__body">
                <p>Standaard blijft elke nieuwe factuur een concept. Controleer de regels en bedragen en maak de factuur definitief. De dagtaak verstuurt daarna de factuur en plant een incasso als de klant daarvoor toestemming heeft gegeven.</p>
                <form ajax="true" action="<?=$h($actions['save'])?>" method="post">
                    <label class="admin-field" style="--cw:12"><span>Maandfacturen automatisch definitief maken en versturen</span><input type="checkbox" name="automatic" value="1"<?=$automatic ? ' checked' : ''?>><small>Uit: eerst handmatig controleren. Aan: alleen nieuwe concepten uit herhaalschema’s worden automatisch goedgekeurd. Bestaande concepten blijven ter controle staan. Berekeningsfouten blokkeren verwerking altijd.</small></label>
                    <div style="--cw:12"><button class="button button-publish" type="submit">Voorkeur opslaan</button></div>
                </form>
            </div>
        </div>
        <div class="panel" style="--cw:6;--cw-sm:12">
            <div class="panel__header"><h3>Goedgekeurde facturen versturen</h3></div>
            <div class="panel__body">
                <?php if (!$ready) { ?><p>De nieuwe workflow moet eerst met een Autowire-scan worden geïnstalleerd.</p><?php } else { ?>
                <p>De dagtaak verwerkt dit automatisch. Je kunt ook nu de goedgekeurde facturen verwerken. Een onbekende of mislukte mailuitkomst wordt niet automatisch opnieuw geprobeerd.</p>
                <button class="button button-secondary" type="button" ajax="true" action="<?=$h($actions['run'])?>" use-waiting-icon alert="Goedgekeurde facturen versturen? Met automatische goedkeuring aan worden ook daarvoor aangemaakte nieuwe concepten definitief gemaakt.">Nu facturen verwerken</button>
                <?php } ?>
            </div>
        </div>
    </grid>
</section>
