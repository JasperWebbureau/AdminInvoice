<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/Flexgrid/src/Html/Table/TableRenderer.php';

$current = [
    'public_id' => 'plan-one', 'name' => 'Maandfactuur', 'customer_public_id' => '',
    'catalog_item_public_id' => '', 'next_issue_date' => '2026-10-01',
    'frequency_months' => 1, 'due_days' => 14, 'active' => 1,
];
$linkedCustomers = [[
    'customer_public_id' => 'customer-one', 'customer_name' => '<script>klant</script>',
    'active_item_count' => 2, 'next_issue_date' => '2026-10-01',
    'active' => 1, 'last_error' => '',
]];
$customerSelector = 'beschikbaar';
$legacy = false;
$items = [];
$plans = [];
$saveAction = 'save-plan';
$runAction = 'run-plans';
$toggleAction = 'toggle-plan';
$enrollFromPlanAction = 'enroll-plan';
$unlinkCustomerAction = 'unlink-plan';
$customerSearchAction = 'search-customers';
$showEditor = true;
$linkedCustomerTable = [
    'columns' => [['key' => 'customer', 'label' => 'Klant']],
    'rows' => [['id' => 'customer-one', 'cells' => ['customer' => '<script>klant</script>']]],
];
ob_start();
include dirname(__DIR__) . '/src/Templates/Recurring/Index.php';
$html = (string)ob_get_clean();

adminInvoiceAssert(strpos($html, 'Gekoppelde klanten (1)') !== false,
    'Schema-editor moet het aantal gekoppelde klanten tonen.');
adminInvoiceAssert(strpos($html, 'data-fg-table') !== false,
    'Gekoppelde klanten moeten via TableRenderer verschijnen.');
adminInvoiceAssert(strpos($html, 'action="search-customers"') !== false
    && strpos($html, 'action="enroll-plan"') !== false,
    'Schema-editor moet zoeken en koppelen als aparte acties tonen.');
adminInvoiceAssert(strpos($html, '<script>klant</script>') === false
    && strpos($html, '&lt;script&gt;klant&lt;/script&gt;') !== false,
    'De klantnaam moet veilig worden weergegeven.');

$current['active'] = 0;
ob_start();
include dirname(__DIR__) . '/src/Templates/Recurring/Index.php';
$pausedHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($pausedHtml, 'Activeer het schema hierboven') !== false
    && strpos($pausedHtml, 'action="enroll-plan"') === false,
    'Bij een gepauzeerd schema moet de koppeling duidelijk uitgeschakeld zijn.');

$showEditor = false;
$planTable = [
    'columns' => [['key' => 'name', 'label' => 'Schema']],
    'rows' => [['id' => 'plan-one', 'cells' => ['name' => 'Maandfactuur']]],
];
ob_start();
include dirname(__DIR__) . '/src/Templates/Recurring/Index.php';
$overviewHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($overviewHtml, 'Maandfactuur') !== false
    && strpos($overviewHtml, 'name="frequency_months"') === false,
    'Het schemaoverzicht mag de grote editor niet bevatten.');

echo "AdminInvoice recurring template tests passed.\n";
