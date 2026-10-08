<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/.env.php';

if (!isset($db['host'], $db['name'], $db['username'], $db['password'])) {
    throw new RuntimeException('Databaseconfiguratie ontbreekt in .env.php.');
}

$connection = new PDO(
    'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
    $db['username'],
    $db['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
unset($db);

/** @return array<string,array<string,mixed>> */
function adminInvoiceSchemaColumns(PDO $connection, string $table): array
{
    $rows = $connection->query('SHOW FULL COLUMNS FROM `' . $table . '`')->fetchAll();
    $columns = [];
    foreach ($rows as $row) {
        $columns[$row['Field']] = $row;
    }

    return $columns;
}

/** @return array<int,array<string,mixed>> */
function adminInvoiceSchemaIndex(PDO $connection, string $table, string $index): array
{
    $statement = $connection->prepare(
        'SELECT `INDEX_NAME` AS `Key_name`, `NON_UNIQUE` AS `Non_unique`, '
        . '`SEQ_IN_INDEX` AS `Seq_in_index`, `COLUMN_NAME` AS `Column_name` '
        . 'FROM information_schema.statistics '
        . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index '
        . 'ORDER BY `Seq_in_index`'
    );
    $statement->execute([':table' => $table, ':index' => $index]);

    return $statement->fetchAll();
}

$invoiceColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice');
$invoiceTypes = [
    'tenant_id' => 'varchar(64)',
    'public_id' => 'varchar(64)',
    'invoice_number' => 'varchar(64)',
    'invoice_sequence_value' => 'bigint',
    'currency' => 'varchar(3)',
    'customer_snapshot' => 'text',
    'billing_address_snapshot' => 'text',
    'net_total_minor' => 'bigint',
    'tax_total_minor' => 'bigint',
    'gross_total_minor' => 'bigint',
];
foreach ($invoiceTypes as $column => $typePrefix) {
    adminInvoiceAssert(isset($invoiceColumns[$column]), 'Databasekolom admin_invoice.' . $column . ' ontbreekt.');
    adminInvoiceAssert(
        strpos(strtolower((string)$invoiceColumns[$column]['Type']), $typePrefix) === 0,
        'Databasekolom admin_invoice.' . $column . ' heeft een onverwacht type.'
    );
}
foreach (['tenant_id', 'public_id', 'currency', 'customer_snapshot', 'billing_address_snapshot', 'net_total_minor'] as $column) {
    adminInvoiceAssert($invoiceColumns[$column]['Null'] === 'NO', 'Databasekolom admin_invoice.' . $column . ' moet NOT NULL zijn.');
}
foreach (['invoice_number', 'invoice_sequence_value', 'source', 'external_id'] as $column) {
    adminInvoiceAssert($invoiceColumns[$column]['Null'] === 'YES', 'Optionele unique kolom ' . $column . ' moet SQL NULL toestaan.');
}

$lineColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_line');
$lineTypes = [
    'tenant_id' => 'varchar(64)',
    'invoice_id' => 'int',
    'quantity_scaled' => 'bigint',
    'unit_price_minor' => 'bigint',
    'tax_rate_basis_points' => 'int',
    'net_total_minor' => 'bigint',
    'tax_total_minor' => 'bigint',
    'gross_total_minor' => 'bigint',
];
foreach ($lineTypes as $column => $typePrefix) {
    adminInvoiceAssert(isset($lineColumns[$column]), 'Databasekolom admin_invoice_line.' . $column . ' ontbreekt.');
    adminInvoiceAssert(
        strpos(strtolower((string)$lineColumns[$column]['Type']), $typePrefix) === 0,
        'Databasekolom admin_invoice_line.' . $column . ' heeft een onverwacht type.'
    );
    adminInvoiceAssert($lineColumns[$column]['Null'] === 'NO', 'Databasekolom admin_invoice_line.' . $column . ' moet NOT NULL zijn.');
}
adminInvoiceAssert(isset($lineColumns['catalog_item_public_id'])
    && strpos(strtolower((string)$lineColumns['catalog_item_public_id']['Type']), 'varchar(64)') === 0,
    'Factuurregel moet de optionele catalogusbron kunnen bewaren.');

$catalogColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_catalog_item');
foreach (['tenant_id', 'public_id', 'name', 'item_type', 'parent_public_id', 'variant_key', 'choose_function', 'description', 'unit_price', 'currency', 'tax_rate', 'tax_category', 'active'] as $column) {
    adminInvoiceAssert(isset($catalogColumns[$column]), 'Cataloguskolom ' . $column . ' ontbreekt.');
}
$recurringColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_recurring');
foreach (['tenant_id', 'public_id', 'customer_public_id', 'catalog_item_public_id', 'next_issue_date', 'frequency_months', 'due_days', 'active'] as $column) {
    adminInvoiceAssert(isset($recurringColumns[$column]), 'Herhaalschemakolom ' . $column . ' ontbreekt.');
}
$memberColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_recurring_member');
foreach (['tenant_id', 'plan_public_id', 'customer_public_id', 'next_issue_date', 'active', 'items_initialized', 'last_error'] as $column) {
    adminInvoiceAssert(isset($memberColumns[$column]), 'Schemakoppelingkolom ' . $column . ' ontbreekt.');
}
$memberItemColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_recurring_member_item');
foreach (['tenant_id', 'plan_public_id', 'customer_public_id', 'catalog_item_public_id', 'active'] as $column) {
    adminInvoiceAssert(isset($memberItemColumns[$column]), 'Schema-itemkolom ' . $column . ' ontbreekt.');
}
$dispatchColumns = adminInvoiceSchemaColumns($connection, 'admin_invoice_dispatch');
foreach (['tenant_id', 'invoice_public_id', 'kind', 'recipient', 'status', 'subject', 'sent_at'] as $column) {
    adminInvoiceAssert(isset($dispatchColumns[$column]), 'Verzendhistoriekolom ' . $column . ' ontbreekt.');
}

$creditColumns = adminInvoiceSchemaColumns($connection, 'admin_credit_note');
$creditTypes = [
    'tenant_id' => 'varchar(64)',
    'public_id' => 'varchar(64)',
    'credit_note_number' => 'varchar(64)',
    'sequence_value' => 'bigint',
    'original_invoice_public_id' => 'varchar(64)',
    'original_invoice_number' => 'varchar(64)',
    'currency' => 'varchar(3)',
    'customer_snapshot' => 'text',
    'billing_address_snapshot' => 'text',
    'issue_date' => 'varchar(10)',
    'reason' => 'text',
    'finalized_at' => 'bigint',
    'net_total_minor' => 'bigint',
    'tax_total_minor' => 'bigint',
    'gross_total_minor' => 'bigint',
    'tax_summary_snapshot' => 'text',
];
foreach ($creditTypes as $column => $typePrefix) {
    adminInvoiceAssert(isset($creditColumns[$column]), 'Databasekolom admin_credit_note.' . $column . ' ontbreekt.');
    adminInvoiceAssert(
        strpos(strtolower((string)$creditColumns[$column]['Type']), $typePrefix) === 0,
        'Databasekolom admin_credit_note.' . $column . ' heeft een onverwacht type.'
    );
    adminInvoiceAssert($creditColumns[$column]['Null'] === 'NO', 'Databasekolom admin_credit_note.' . $column . ' moet NOT NULL zijn.');
}

$creditLineColumns = adminInvoiceSchemaColumns($connection, 'admin_credit_note_line');
$creditLineTypes = [
    'tenant_id' => 'varchar(64)',
    'credit_note_id' => 'int',
    'quantity_scaled' => 'bigint',
    'unit_price_minor' => 'bigint',
    'tax_rate_basis_points' => 'int',
    'net_total_minor' => 'bigint',
    'tax_total_minor' => 'bigint',
    'gross_total_minor' => 'bigint',
];
foreach ($creditLineTypes as $column => $typePrefix) {
    adminInvoiceAssert(isset($creditLineColumns[$column]), 'Databasekolom admin_credit_note_line.' . $column . ' ontbreekt.');
    adminInvoiceAssert(
        strpos(strtolower((string)$creditLineColumns[$column]['Type']), $typePrefix) === 0,
        'Databasekolom admin_credit_note_line.' . $column . ' heeft een onverwacht type.'
    );
    adminInvoiceAssert($creditLineColumns[$column]['Null'] === 'NO', 'Databasekolom admin_credit_note_line.' . $column . ' moet NOT NULL zijn.');
}

$expectedIndexes = [
    ['admin_invoice', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_invoice', 'tenant_invoice_number', ['tenant_id', 'invoice_number'], true],
    ['admin_invoice', 'tenant_source_external', ['tenant_id', 'source', 'external_id'], true],
    ['admin_invoice', 'tenant_status_issue', ['tenant_id', 'status', 'issue_date'], false],
    ['admin_invoice_dispatch', 'tenant_invoice_kind', ['tenant_id', 'invoice_public_id', 'kind'], true],
    ['admin_invoice_line', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_invoice_line', 'tenant_invoice_position', ['tenant_id', 'invoice_id', 'position'], true],
    ['admin_invoice_line', 'tenant_invoice', ['tenant_id', 'invoice_id'], false],
    ['admin_invoice_catalog_item', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_invoice_catalog_item', 'tenant_active_name', ['tenant_id', 'active', 'name'], false],
    ['admin_invoice_catalog_item', 'tenant_parent', ['tenant_id', 'parent_public_id'], false],
    ['admin_invoice_recurring', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_invoice_recurring', 'tenant_due', ['tenant_id', 'active', 'next_issue_date'], false],
    ['admin_invoice_recurring_member', 'tenant_plan_customer', ['tenant_id', 'plan_public_id', 'customer_public_id'], true],
    ['admin_invoice_recurring_member', 'tenant_member_due', ['tenant_id', 'active', 'next_issue_date'], false],
    ['admin_invoice_recurring_member', 'tenant_member_customer', ['tenant_id', 'customer_public_id'], false],
    ['admin_invoice_recurring_member_item', 'tenant_assignment_item', ['tenant_id', 'plan_public_id', 'customer_public_id', 'catalog_item_public_id'], true],
    ['admin_invoice_recurring_member_item', 'tenant_assignment', ['tenant_id', 'plan_public_id', 'customer_public_id'], false],
    ['admin_credit_note', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_credit_note', 'tenant_credit_number', ['tenant_id', 'credit_note_number'], true],
    ['admin_credit_note', 'tenant_original_invoice', ['tenant_id', 'original_invoice_public_id'], false],
    ['admin_credit_note', 'tenant_issue_date', ['tenant_id', 'issue_date'], false],
    ['admin_credit_note_line', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_credit_note_line', 'tenant_credit_position', ['tenant_id', 'credit_note_id', 'position'], true],
    ['admin_credit_note_line', 'tenant_credit_note', ['tenant_id', 'credit_note_id'], false],
];
foreach ($expectedIndexes as $expectedIndex) {
    list($table, $name, $columns, $unique) = $expectedIndex;
    $rows = adminInvoiceSchemaIndex($connection, $table, $name);
    adminInvoiceAssert(array_column($rows, 'Column_name') === $columns, 'Index ' . $table . '.' . $name . ' heeft een onverwachte kolomvolgorde.');
    foreach ($rows as $row) {
        adminInvoiceAssert((int)$row['Non_unique'] === ($unique ? 0 : 1), 'Index ' . $table . '.' . $name . ' heeft onverwachte uniqueness.');
    }
}

echo "AdminInvoice database schema tests passed.\n";
