<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\CreditNotePresenter;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;

$currency = Currency::euro();
$line = new InvoiceLine(
    'credit-ui-line',
    '<Gecrediteerde regel>',
    Quantity::fromDecimal('1'),
    'stuk',
    Money::fromDecimal('-100.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
);
$creditNote = new CreditNote(
    'credit-ui-note',
    new TenantId('credit-ui-tenant'),
    'CN-2026-0001',
    1,
    'credit-ui-invoice',
    '2026-0001',
    $currency,
    new PartySnapshot('<Creditklant>', 'Inkoop', 'credit@example.test'),
    new AddressSnapshot('Markt 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    '<Opdracht geannuleerd>',
    [$line],
    (new InvoiceCalculator())->calculateLines($currency, [$line]),
    1770004321
);

$viewModel = (new CreditNotePresenter())->present($creditNote);
extract($viewModel, EXTR_OVERWRITE);
ob_start();
include dirname(__DIR__) . '/src/Templates/CreditNote/Content.php';
$content = (string)ob_get_clean();

adminInvoiceAssert(strpos($content, 'CN-2026-0001') !== false, 'Creditnotadetail moet het definitieve nummer tonen.');
adminInvoiceAssert(strpos($content, '2026-0001') !== false, 'Creditnotadetail moet het bronfactuurnummer tonen.');
adminInvoiceAssert(strpos($content, '&lt;Creditklant&gt;') !== false, 'Creditnotadetail moet de klantsnapshot escapen.');
adminInvoiceAssert(strpos($content, '&lt;Gecrediteerde regel&gt;') !== false, 'Creditnotadetail moet regels escapen.');
adminInvoiceAssert(strpos($content, '&lt;Opdracht geannuleerd&gt;') !== false, 'Creditnotadetail moet de reden escapen.');
adminInvoiceAssert(strpos($content, '€ -121,00') !== false, 'Creditnotadetail moet het negatieve totaal tonen.');
adminInvoiceAssert(strpos($content, '<form') === false, 'Een creditnotadetail moet volledig read-only zijn.');

$displayNumber = $viewModel['displayNumber'];
$pdfUrl = '/credit.pdf';
$invoiceUrl = '/invoice';
$overviewUrl = '/invoices';
ob_start();
include dirname(__DIR__) . '/src/Templates/CreditNote/Index.php';
$index = (string)ob_get_clean();
$mode = 'credit';
ob_start();
include dirname(__DIR__) . '/src/Templates/PageActions.php';
$header = (string)ob_get_clean();
adminInvoiceAssert(strpos($header, '/credit.pdf') !== false, 'Creditnotadetail moet een on-demand PDF-link in de globale header tonen.');
adminInvoiceAssert(strpos($header, '/invoice') !== false, 'Creditnotadetail moet vanuit de globale header teruglinken naar de bronfactuur.');

$displayNumber = '2026-0001';
$statusLabel = 'Definitief';
$canCredit = true;
$creditAction = 'credit-event';
$publicId = 'credit-ui-invoice';
$pdfUrl = '/invoice.pdf';
$overviewUrl = '/invoices';
$content = '<div>Factuur</div>';
ob_start();
include dirname(__DIR__) . '/src/Templates/Detail/Index.php';
$invoiceDetail = (string)ob_get_clean();
adminInvoiceAssert(strpos($invoiceDetail, 'ajax="true"') !== false, 'Volledige creditering moet Flexgrids declaratieve AJAX gebruiken.');
adminInvoiceAssert(strpos($invoiceDetail, 'action="credit-event"') !== false, 'Creditformulier moet naar de geregistreerde AjaxEvent wijzen.');
adminInvoiceAssert(strpos($invoiceDetail, 'name="reason"') !== false && strpos($invoiceDetail, 'required') !== false, 'Creditactie moet een verplichte reden vragen.');

$script = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Detail/Js/CreditNote.js');
adminInvoiceAssert(strpos($script, 'class AdminInvoiceCreditDialog') !== false, 'Creditdialoog moet als vanilla-JS-class zijn opgebouwd.');
adminInvoiceAssert(strpos($script, 'showModal') !== false, 'Creditdialoog moet het native dialog-element gebruiken.');
foreach (['jQuery', '$(', 'fetch('] as $forbidden) {
    adminInvoiceAssert(strpos($script, $forbidden) === false, 'Creditdialoog mag geen eigen jQuery/fetch-laag bevatten: ' . $forbidden);
}

echo "AdminInvoice credit note UI tests passed.\n";
