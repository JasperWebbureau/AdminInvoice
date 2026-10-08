<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 3);
$adminUi = (string)file_get_contents($projectRoot . '/flexgrid/src/Html/Admin/Css/AdminUi.scss');
$createTemplate = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Create/Index.php');
$editorTemplate = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Editor/Content.php');
$overviewTemplate = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Invoices/Content.php');
$detailTemplate = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Detail/Index.php');

adminInvoiceAssert(
    strpos($adminUi, '.admin-form-grid') !== false
    && strpos($adminUi, '--cw: 6;') !== false
    && strpos($adminUi, '--cw-sm: 12;') !== false
    && strpos($adminUi, '.admin-form > *') === false,
    'Admin-formuliervelden moeten Flexgrids bestaande twaalfkolomsgrid via --cw gebruiken.'
);
adminInvoiceAssert(
    strpos($adminUi, 'max-width: none;') !== false,
    'Admin-formulieren moeten de beschikbare adminbreedte gebruiken.'
);
adminInvoiceAssert(
    strpos($createTemplate, 'class="admin-form admin-invoice-create-form"') !== false,
    'Het aanmaakformulier moet de gedeelde admin-formulierbasis gebruiken.'
);
adminInvoiceAssert(
    strpos($createTemplate, '<grid') !== false
    && strpos($createTemplate, 'panel__body admin-panel__body admin-form-grid fluid') !== false,
    'Panelen en velden van de factuur moeten in Flexgrid-grids staan.'
);
adminInvoiceAssert(
    strpos($editorTemplate, 'class="admin-form admin-invoice-details"') !== false
    && strpos($editorTemplate, 'class="admin-form admin-invoice-line-form"') !== false,
    'De editorformulieren moeten de gedeelde admin-formulierbasis gebruiken.'
);
adminInvoiceAssert(
    strpos($overviewTemplate, 'class="admin-form admin-invoice-results"') !== false,
    'Het overzichtsformulier moet de gedeelde admin-formulierbasis gebruiken.'
);
adminInvoiceAssert(
    strpos($detailTemplate, 'class="admin-form admin-credit-form"') !== false,
    'Ook de creditnotadialoog moet de gedeelde admin-formulierbasis gebruiken.'
);

echo "Admin form layout tests passed.\n";
