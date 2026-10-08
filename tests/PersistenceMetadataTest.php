<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 4);
$frameworkSource = $projectRoot . '/flexgrid/flexgrid/src';

if (!class_exists('Repository\\RepositoryEntity')) {
    eval('namespace Repository; class RepositoryEntity {}');
}

require_once $frameworkSource . '/Utils/_Enum.php';
require_once $frameworkSource . '/Utils/_String.php';
require_once $frameworkSource . '/Database/Column/DefaultColumnLenth.php';
require_once $frameworkSource . '/Database/Column/ColumnType.php';
require_once $frameworkSource . '/Database/Column/Column.php';
require_once $frameworkSource . '/Autowire/Definition/PropertyDefinition.php';
require_once $frameworkSource . '/Autowire/Definition/EntityDefinition.php';
require_once $frameworkSource . '/Autowire/Scanner/EntityScanner.php';
require_once $frameworkSource . '/Autowire/Schema/SchemaIndexDefinition.php';
require_once dirname(__DIR__) . '/src/Entity/InvoiceRecord.php';
require_once dirname(__DIR__) . '/src/Entity/InvoiceLineRecord.php';
require_once dirname(__DIR__) . '/src/Entity/CreditNoteRecord.php';
require_once dirname(__DIR__) . '/src/Entity/CreditNoteLineRecord.php';
require_once dirname(__DIR__) . '/src/Entity/CatalogItemRecord.php';
require_once dirname(__DIR__) . '/src/Entity/RecurringInvoiceRecord.php';
require_once dirname(__DIR__) . '/src/Entity/RecurringInvoiceMemberRecord.php';
require_once dirname(__DIR__) . '/src/Entity/RecurringInvoiceMemberItemRecord.php';
require_once dirname(__DIR__) . '/src/Entity/InvoiceDispatchRecord.php';

use Flexgrid\Autowire\Scanner\EntityScanner;
use Flexgrid\Autowire\Schema\SchemaIndexDefinition;
use Flexgrid\Modules\AdminInvoice\Entity\InvoiceLineRecord;
use Flexgrid\Modules\AdminInvoice\Entity\InvoiceRecord;
use Flexgrid\Modules\AdminInvoice\Entity\CreditNoteRecord;
use Flexgrid\Modules\AdminInvoice\Entity\CreditNoteLineRecord;
use Flexgrid\Modules\AdminInvoice\Entity\CatalogItemRecord;
use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceRecord;
use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceMemberRecord;
use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceMemberItemRecord;
use Flexgrid\Modules\AdminInvoice\Entity\InvoiceDispatchRecord;

$scanner = new EntityScanner();
require_once dirname(__DIR__) . '/src/Entity/InvoiceProcessingRecord.php';
$processingDefinition = $scanner->parseEntity(\Flexgrid\Modules\AdminInvoice\Entity\InvoiceProcessingRecord::class,
    dirname(__DIR__) . '/src/Entity/InvoiceProcessingRecord.php');
adminInvoiceAssert($processingDefinition->getTableName() === 'admin_invoice_processing', 'Verwerking heeft een eigen tabel.');
$processingIndex = SchemaIndexDefinition::fromEntityAnnotation($processingDefinition, 'tenant_invoice',
    $processingDefinition->getClassAnnotations()['Index']['tenant_invoice']);
adminInvoiceAssert(strpos($processingIndex->buildCreateSql('admin_invoice_processing'), 'UNIQUE INDEX') !== false,
    'Een factuur mag maar eenmaal in de tenantgebonden verwerkingswachtrij staan.');
$invoiceFile = dirname(__DIR__) . '/src/Entity/InvoiceRecord.php';
$lineFile = dirname(__DIR__) . '/src/Entity/InvoiceLineRecord.php';
$invoiceDefinition = $scanner->parseEntity(InvoiceRecord::class, $invoiceFile);
$lineDefinition = $scanner->parseEntity(InvoiceLineRecord::class, $lineFile);
$creditFile = dirname(__DIR__) . '/src/Entity/CreditNoteRecord.php';
$creditLineFile = dirname(__DIR__) . '/src/Entity/CreditNoteLineRecord.php';
$creditDefinition = $scanner->parseEntity(CreditNoteRecord::class, $creditFile);
$creditLineDefinition = $scanner->parseEntity(CreditNoteLineRecord::class, $creditLineFile);

adminInvoiceAssert($invoiceDefinition->getTableName() === 'admin_invoice', 'InvoiceRecord moet zijn eigen moduletabel bezitten.');
adminInvoiceAssert($lineDefinition->getTableName() === 'admin_invoice_line', 'InvoiceLineRecord moet zijn eigen moduletabel bezitten.');
adminInvoiceAssert($creditDefinition->getTableName() === 'admin_credit_note', 'CreditNoteRecord moet zijn eigen moduletabel bezitten.');
adminInvoiceAssert($creditLineDefinition->getTableName() === 'admin_credit_note_line', 'CreditNoteLineRecord moet zijn eigen moduletabel bezitten.');

$invoiceProperties = $invoiceDefinition->getProperties();
foreach (['tenantId', 'publicId', 'invoiceSequenceValue', 'currency', 'customerSnapshot', 'billingAddressSnapshot', 'netTotalMinor', 'taxTotalMinor', 'grossTotalMinor'] as $property) {
    adminInvoiceAssert(isset($invoiceProperties[$property]), 'InvoiceRecord mist property ' . $property . '.');
}
adminInvoiceAssert($invoiceProperties['netTotalMinor']->getDatabaseType() === 'BIGINT', 'Factuurtotalen moeten als BIGINT worden opgeslagen.');
adminInvoiceAssert($invoiceProperties['invoiceSequenceValue']->getDatabaseType() === 'BIGINT', 'Nummerreekswaarde moet als BIGINT worden opgeslagen.');
adminInvoiceAssert($invoiceProperties['customerSnapshot']->getDatabaseType() === 'TEXT', 'Klantsnapshot moet documentdata zelfstandig bewaren.');

$invoiceAnnotations = $invoiceDefinition->getClassAnnotations();
$publicIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $invoiceDefinition,
    'tenant_public',
    $invoiceAnnotations['Index']['tenant_public']
);
adminInvoiceAssert(
    $publicIndex->buildCreateSql('admin_invoice')
        === 'ALTER TABLE `admin_invoice` ADD UNIQUE INDEX `tenant_public` (`tenant_id`, `public_id`)',
    'Publieke factuur-id moet per tenant uniek zijn.'
);

$numberIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $invoiceDefinition,
    'tenant_invoice_number',
    $invoiceAnnotations['Index']['tenant_invoice_number']
);
adminInvoiceAssert(strpos($numberIndex->buildCreateSql('admin_invoice'), 'UNIQUE INDEX') !== false, 'Factuurnummerindex moet unique zijn.');

$lineProperties = $lineDefinition->getProperties();
foreach (['tenantId', 'invoiceId', 'quantityScaled', 'unitPriceMinor', 'taxRateBasisPoints', 'catalogItemPublicId', 'position'] as $property) {
    adminInvoiceAssert(isset($lineProperties[$property]), 'InvoiceLineRecord mist property ' . $property . '.');
}
adminInvoiceAssert($lineProperties['quantityScaled']->getDatabaseType() === 'BIGINT', 'Geschaalde hoeveelheid moet als BIGINT worden opgeslagen.');

$catalogDefinition = $scanner->parseEntity(
    CatalogItemRecord::class, dirname(__DIR__) . '/src/Entity/CatalogItemRecord.php'
);
$recurringDefinition = $scanner->parseEntity(
    RecurringInvoiceRecord::class, dirname(__DIR__) . '/src/Entity/RecurringInvoiceRecord.php'
);
$dispatchDefinition = $scanner->parseEntity(
    InvoiceDispatchRecord::class, dirname(__DIR__) . '/src/Entity/InvoiceDispatchRecord.php'
);
adminInvoiceAssert($dispatchDefinition->getTableName() === 'admin_invoice_dispatch', 'Verzendhistorie heeft een eigen tabel.');
$dispatchAnnotations = $dispatchDefinition->getClassAnnotations();
$dispatchIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $dispatchDefinition, 'tenant_invoice_kind', $dispatchAnnotations['Index']['tenant_invoice_kind']
);
adminInvoiceAssert(strpos($dispatchIndex->buildCreateSql('admin_invoice_dispatch'), 'UNIQUE INDEX') !== false,
    'Factuurmail en herinnering mogen per factuur slechts eenmaal worden gestart.');
$memberDefinition = $scanner->parseEntity(
    RecurringInvoiceMemberRecord::class, dirname(__DIR__) . '/src/Entity/RecurringInvoiceMemberRecord.php'
);
$memberItemDefinition = $scanner->parseEntity(
    RecurringInvoiceMemberItemRecord::class, dirname(__DIR__) . '/src/Entity/RecurringInvoiceMemberItemRecord.php'
);
adminInvoiceAssert($catalogDefinition->getTableName() === 'admin_invoice_catalog_item', 'Catalogus heeft een eigen tabel.');
foreach (['itemType', 'parentPublicId', 'variantKey', 'chooseFunction'] as $property) {
    adminInvoiceAssert(isset($catalogDefinition->getProperties()[$property]),
        'Keuzegroep mist cataloguseigenschap ' . $property . '.');
}
adminInvoiceAssert($recurringDefinition->getTableName() === 'admin_invoice_recurring', 'Herhaalschema heeft een eigen tabel.');
adminInvoiceAssert($memberDefinition->getTableName() === 'admin_invoice_recurring_member', 'Klantkoppeling heeft een eigen tabel.');
adminInvoiceAssert(isset($memberDefinition->getProperties()['nextIssueDate']), 'Klantkoppeling bewaart een eigen factuurdatum.');
adminInvoiceAssert(isset($memberDefinition->getProperties()['itemsInitialized']), 'Bestaande standaarditems blijven herkenbaar.');
adminInvoiceAssert($memberItemDefinition->getTableName() === 'admin_invoice_recurring_member_item',
    'Items per klant en schema hebben een eigen tabel.');
foreach ([$catalogDefinition, $recurringDefinition] as $definition) {
    $index = SchemaIndexDefinition::fromEntityAnnotation(
        $definition, 'tenant_public', $definition->getClassAnnotations()['Index']['tenant_public']
    );
    adminInvoiceAssert(strpos($index->buildCreateSql($definition->getTableName()), 'UNIQUE INDEX') !== false,
        'Nieuwe records vereisen een tenantgebonden publieke id.');
}

$creditProperties = $creditDefinition->getProperties();
foreach (['tenantId', 'publicId', 'creditNoteNumber', 'sequenceValue', 'originalInvoicePublicId', 'customerSnapshot', 'netTotalMinor', 'taxTotalMinor', 'grossTotalMinor'] as $property) {
    adminInvoiceAssert(isset($creditProperties[$property]), 'CreditNoteRecord mist property ' . $property . '.');
}
adminInvoiceAssert($creditProperties['sequenceValue']->getDatabaseType() === 'BIGINT', 'Creditnotareekswaarde moet als BIGINT worden opgeslagen.');
adminInvoiceAssert($creditProperties['grossTotalMinor']->getDatabaseType() === 'BIGINT', 'Creditnotatotalen moeten signed BIGINT blijven.');

$creditAnnotations = $creditDefinition->getClassAnnotations();
$creditNumberIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $creditDefinition,
    'tenant_credit_number',
    $creditAnnotations['Index']['tenant_credit_number']
);
adminInvoiceAssert(strpos($creditNumberIndex->buildCreateSql('admin_credit_note'), 'UNIQUE INDEX') !== false, 'Creditnotanummer moet per tenant uniek zijn.');

$creditLineProperties = $creditLineDefinition->getProperties();
foreach (['tenantId', 'creditNoteId', 'quantityScaled', 'unitPriceMinor', 'taxRateBasisPoints', 'position'] as $property) {
    adminInvoiceAssert(isset($creditLineProperties[$property]), 'CreditNoteLineRecord mist property ' . $property . '.');
}

$sourceIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src'));
$forbidden = ['Flexgrid\\Modules\\AdminCustomer\\', 'Flexgrid\\Modules\\AdminPayment\\', 'Flexgrid\\Modules\\AdminQuote\\', 'Flexgrid\\Modules\\Webshop\\', 'App\\Administration\\'];
foreach ($sourceIterator as $sourceFile) {
    if (!$sourceFile->isFile() || strtolower($sourceFile->getExtension()) !== 'php') {
        continue;
    }
    $content = (string)file_get_contents($sourceFile->getPathname());
    $normalizedPath = str_replace('\\', '/', $sourceFile->getPathname());
    foreach ($forbidden as $namespace) {
        if ($namespace === 'Flexgrid\\Modules\\AdminCustomer\\'
            && strpos($normalizedPath, '/Integration/Customer/') !== false) {
            continue;
        }
        if ($namespace === 'Flexgrid\\Modules\\AdminQuote\\'
            && strpos($normalizedPath, '/Integration/Quote/') !== false) {
            continue;
        }
        adminInvoiceAssert(strpos($content, $namespace) === false, 'Verboden module-import gevonden: ' . $namespace);
    }
}

echo "AdminInvoice persistence metadata tests passed.\n";
