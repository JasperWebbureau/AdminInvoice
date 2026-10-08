<?php
$h = $h ?? function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<button class="button button-publish" type="button" data-admin-invoice-finalize ajax="true" action="<?=$h($finalizeAction ?? '')?>" public_id="<?=$h($publicId ?? '')?>" alert="Na definitief maken kan deze factuur niet meer worden gewijzigd. Doorgaan?" use-waiting-icon<?=($canFinalize ?? false) ? '' : ' disabled title="Voeg eerst minimaal één factuurregel toe"'?>>
    <i class="fas fa-lock" aria-hidden="true"></i> Definitief maken
</button>
