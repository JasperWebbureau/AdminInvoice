<section class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="admin-page-header__eyebrow">Facturatie</p>
            <h1>Factuuritems</h1>
            <p class="admin-page-header__intro">Bekijk de standaardregels en keuzegroepen die je voor nieuwe factuurconcepten kunt gebruiken. Open een item om het te bewerken; bestaande facturen veranderen niet mee.</p>
        </div>
    </div>
    <grid>
        <div class="panel" style="--cw:12">
            <div class="panel__header"><h3>Opgeslagen items</h3></div>
            <div class="panel__body">
                <?=new \Flexgrid\Html\Table\TableRenderer($table)?>
            </div>
        </div>
    </grid>
</section>
