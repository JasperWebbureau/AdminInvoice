<?php
$h = $h ?? static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<a class="button button-secondary" data-admin-invoice-preview href="<?=$h($previewUrl ?? '')?>" target="_blank" rel="noopener" title="Bekijk de opgeslagen conceptfactuur als PDF; er wordt niets definitief gemaakt">
    <i class="fas fa-file-pdf" aria-hidden="true"></i> Concept-PDF bekijken
</a>
