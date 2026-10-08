<?php
$h = function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<form class="admin-form admin-invoice-results" data-admin-invoice-filters ajax="true" action="<?=$h($refreshAction ?? '')?>" method="post">
    <input type="hidden" name="sort" value="<?=$h($query->getSort())?>">
    <input type="hidden" name="direction" value="<?=$h($query->getDirection())?>">
    <input type="hidden" name="page" value="<?=$h($result->getPage())?>">

    <div class="admin-summary-grid admin-invoice-summary" style="--cw:12">
        <?php foreach ($summaryCards as $card) { ?>
            <article class="admin-stat-card">
                <span class="admin-tone-icon is-<?=$h($card['tone'])?>">
                    <i class="<?=$h($card['icon'])?>"></i>
                </span>
                <span class="admin-stat-card__content">
                    <span class="admin-stat-card__label"><?=$h($card['label'])?></span>
                    <strong class="admin-stat-card__value"><?=$h($card['value'])?></strong>
                    <small class="admin-stat-card__meta"><?=$h($card['meta'])?></small>
                </span>
            </article>
        <?php } ?>
    </div>

    <grid style="--cw:12">
    <section class="panel admin-panel admin-invoice-list-panel" style="--cw:12">
        <div class="panel__body admin-panel__body admin-panel__body--flush">
            <div class="admin-invoice-toolbar">
                <label class="admin-invoice-search">
                    <span class="fg-table__visually-hidden"><?=t('admin_invoice_search_label', 'Documenten zoeken')?></span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" name="q" value="<?=$h($query->getSearch())?>" placeholder="<?=t('admin_invoice_search_placeholder', 'Zoek op nummer, klant, reden of opmerking…')?>" autocomplete="off">
                </label>

                <select name="document_type" aria-label="<?=t('admin_invoice_document_type_filter', 'Filter op documenttype')?>">
                    <?php foreach ($documentTypeOptions as $value => $label) { ?>
                        <option value="<?=$h($value)?>"<?=$query->getDocumentType() === $value ? ' selected' : ''?>><?=$h($label)?></option>
                    <?php } ?>
                </select>

                <select name="status" aria-label="<?=t('admin_invoice_status_filter', 'Filter op documentstatus')?>">
                    <?php foreach ($statusOptions as $value => $label) { ?>
                        <option value="<?=$h($value)?>"<?=$query->getStatus() === $value ? ' selected' : ''?>><?=$h($label)?></option>
                    <?php } ?>
                </select>

                <select name="payment_status" aria-label="<?=t('admin_invoice_payment_filter', 'Filter op betaalstatus')?>">
                    <?php foreach ($paymentStatusOptions as $value => $label) { ?>
                        <option value="<?=$h($value)?>"<?=$query->getPaymentStatus() === $value ? ' selected' : ''?>><?=$h($label)?></option>
                    <?php } ?>
                </select>

                <button class="button button-secondary admin-invoice-reset" type="button" data-admin-invoice-reset title="<?=t('admin_invoice_reset_filters', 'Filters wissen')?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                    <span><?=t('admin_invoice_reset', 'Wissen')?></span>
                </button>
            </div>

            <?php
            $tableRenderer = new \Flexgrid\Html\Table\TableRenderer($table);
            echo $tableRenderer->render();
            ?>

            <div class="admin-invoice-pagination">
                <label>
                    <span><?=t('admin_invoice_show', 'Toon')?></span>
                    <select name="per_page" aria-label="<?=t('admin_invoice_page_size', 'Aantal resultaten per pagina')?>">
                        <?php foreach ($pageSizes as $pageSize) { ?>
                            <option value="<?=$h($pageSize)?>"<?=$query->getPerPage() === $pageSize ? ' selected' : ''?>><?=$h($pageSize)?></option>
                        <?php } ?>
                    </select>
                    <span><?=t('admin_invoice_results', 'resultaten')?></span>
                </label>

                <span class="admin-invoice-pagination__count">
                    <?=$h($result->getFirstPosition())?>–<?=$h($result->getLastPosition())?> <?=t('admin_invoice_of', 'van')?> <?=$h($result->getTotal())?>
                </span>

                <nav class="admin-invoice-pagination__pages" aria-label="<?=t('admin_invoice_pagination', 'Documentpagina’s')?>">
                    <button type="button" data-admin-invoice-page="<?=$h(max(1, $result->getPage() - 1))?>"<?=$result->getPage() <= 1 ? ' disabled' : ''?> aria-label="<?=t('admin_invoice_previous', 'Vorige pagina')?>">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <?php foreach ($pages as $page) { ?>
                        <button type="button" data-admin-invoice-page="<?=$h($page)?>"<?=$page === $result->getPage() ? ' class="is-active" aria-current="page"' : ''?>><?=$h($page)?></button>
                    <?php } ?>
                    <button type="button" data-admin-invoice-page="<?=$h(min($result->getTotalPages(), $result->getPage() + 1))?>"<?=$result->getPage() >= $result->getTotalPages() ? ' disabled' : ''?> aria-label="<?=t('admin_invoice_next', 'Volgende pagina')?>">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </nav>
            </div>
        </div>
    </section>
    </grid>
</form>
