<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$javascript = (string)file_get_contents(
    dirname(__DIR__) . '/src/Templates/Invoices/Js/Invoices.js'
);

adminInvoiceAssert(
    strpos($javascript, 'class AdminInvoiceOverview') !== false,
    'Invoice-overzichtinteractie moet als JavaScript-class zijn opgebouwd.'
);
adminInvoiceAssert(
    strpos($javascript, 'requestSubmit()') !== false,
    'Invoice-overzicht moet het declaratieve Flexgrid AJAX-formulier submitten.'
);
adminInvoiceAssert(
    strpos($javascript, "$('body')") === false && strpos($javascript, 'jQuery') === false,
    'Invoice-module mag niet direct van jQuery afhankelijk zijn.'
);
adminInvoiceAssert(
    strpos($javascript, 'fetch(') === false && strpos($javascript, 'XMLHttpRequest') === false,
    'Invoice-module mag geen eigen AJAX-transport naast de Flexgrid dispatcher introduceren.'
);
adminInvoiceAssert(
    strpos($javascript, "setValue(form, 'document_type', '')") !== false,
    'Filters wissen moet ook het documenttype terugzetten.'
);

$editorJavaScript = (string)file_get_contents(
    dirname(__DIR__) . '/src/Templates/Editor/Js/Editor.js'
);
adminInvoiceAssert(
    strpos($editorJavaScript, 'class AdminInvoiceEditor') !== false,
    'Invoice-editorinteractie moet als JavaScript-class zijn opgebouwd.'
);
adminInvoiceAssert(
    strpos($editorJavaScript, 'requireHeaderSave()') !== false
    && strpos($editorJavaScript, '[data-admin-invoice-finalize]') !== false
    && strpos($editorJavaScript, '[data-admin-invoice-preview]') !== false
    && strpos($editorJavaScript, "addEventListener('input'") !== false,
    'Onopgeslagen kopgegevens moeten finalisatie en de PDF-preview blokkeren tot de AJAX-save is geslaagd.'
);
adminInvoiceAssert(
    strpos($editorJavaScript, "addEventListener('change'") !== false
    && strpos($editorJavaScript, 'requestSubmit()') !== false,
    'Inline regels moeten via native onchange en het declaratieve formulier opslaan.'
);
adminInvoiceAssert(
    strpos($editorJavaScript, '$(') === false
    && strpos($editorJavaScript, 'jQuery') === false
    && strpos($editorJavaScript, 'fetch(') === false
    && strpos($editorJavaScript, 'XMLHttpRequest') === false,
    'Invoice-editor mag geen directe jQuery- of eigen AJAX-transportafhankelijkheid hebben.'
);

$customerSelectorJavaScript = (string)file_get_contents(
    dirname(__DIR__) . '/src/Templates/CustomerSelector/Js/CustomerSelector.js'
);
adminInvoiceAssert(
    strpos($customerSelectorJavaScript, 'class AdminInvoiceCustomerSelector') !== false
    && strpos($customerSelectorJavaScript, 'requestSubmit()') !== false,
    'Klantselector moet als vanilla class het declaratieve zoekformulier submitten.'
);
adminInvoiceAssert(
    strpos($customerSelectorJavaScript, 'customer[source_public_id]') !== false
    && strpos($customerSelectorJavaScript, 'address[line_1]') !== false,
    'Selectie moet bron-id en snapshotvelden invullen.'
);
adminInvoiceAssert(
    strpos($customerSelectorJavaScript, 'markChanged(form)') !== false
    && strpos($customerSelectorJavaScript, "new Event('change', {bubbles: true})") !== false,
    'Klantselectie moet de bestaande onopgeslagen-wijzigingsbewaking activeren.'
);
adminInvoiceAssert(
    strpos($customerSelectorJavaScript, '$(') === false
    && strpos($customerSelectorJavaScript, 'jQuery') === false
    && strpos($customerSelectorJavaScript, 'fetch(') === false
    && strpos($customerSelectorJavaScript, 'XMLHttpRequest') === false,
    'Klantselector mag geen directe jQuery- of eigen AJAX-transportafhankelijkheid hebben.'
);

$controller = (string)file_get_contents(
    dirname(__DIR__) . '/src/Controller/AdminInvoiceController.php'
);
adminInvoiceAssert(
    strpos($controller, "PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Js/CustomerSelector.js')") !== false
    && strpos($controller, "PageResponse::addAsset('Flexgrid/Modules/AdminInvoice/src/Templates/CustomerSelector/Css/CustomerSelector.scss')") !== false,
    'De klantselectorassets moeten expliciet worden geregistreerd en mogen niet van een template-cache-entry afhangen.'
);

echo "AdminInvoice JavaScript tests passed.\n";
