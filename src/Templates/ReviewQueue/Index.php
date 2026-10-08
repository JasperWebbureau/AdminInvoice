<section class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow">Facturatie</p>
            <h1>Concepten controleren</h1>
            <p class="admin-page-header__intro">Hier staan de nieuwste conceptfacturen. Controleer de klant, regels en PDF voordat je een factuur definitief maakt. Automatisch goedkeuren staat standaard uit; verzending van goedgekeurde maandfacturen beheer je onder Factuurverwerking.</p>
        </div>
    </div>
    <grid>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3>Te controleren concepten (<?= (int)($rowCount ?? 0) ?>)</h3></div>
            <div class="panel__body">
                <?=new \Flexgrid\Html\Table\TableRenderer($table ?? [])?>
            </div>
        </div>
    </grid>
</section>
