<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceEditorPresenter;

$currency = Currency::euro();
$invoice = new Invoice(
    'detail-invoice',
    new TenantId('detail-tenant'),
    $currency,
    new PartySnapshot('<Definitieve klant>', 'Inkoop', 'inkoop@example.test'),
    new AddressSnapshot('Markt 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18'),
    'project-42',
    '<Interne notitie>'
);
$invoice->addLine(new InvoiceLine(
    'detail-line',
    '<Definitieve regel>',
    Quantity::fromDecimal('1'),
    'stuk',
    Money::fromDecimal('100.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));
$calculator = new InvoiceCalculator();
$invoice->finalize('2026-0001', 1, 1770004321, $calculator->calculate($invoice));
$invoice->correctIssueDate(new CalendarDate('2026-09-21'));
adminInvoiceAssert($invoice->getInvoiceNumber() === '2026-0001', 'Datumcorrectie mag het definitieve factuurnummer niet wijzigen.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($invoice): void {
    $invoice->correctIssueDate(new CalendarDate('2026-11-01'));
}, 'Factuurdatum mag niet na de vervaldatum liggen.');
$viewModel = (new InvoiceEditorPresenter($calculator))->present($invoice, []);
$viewModel['correctIssueDateAction'] = 'correct-date-event';

extract($viewModel, EXTR_OVERWRITE);
ob_start();
include dirname(__DIR__) . '/src/Templates/Detail/Content.php';
$html = (string)ob_get_clean();

adminInvoiceAssert(strpos($html, '2026-0001') !== false, 'Detail moet het definitieve factuurnummer tonen.');
adminInvoiceAssert(strpos($html, 'Definitief gemaakt') !== false, 'Detail moet het finalisatietijdstip tonen.');
adminInvoiceAssert(strpos($html, '&lt;Definitieve klant&gt;') !== false, 'Detail moet klantsnapshot escaped tonen.');
adminInvoiceAssert(strpos($html, '&lt;Definitieve regel&gt;') !== false, 'Detail moet factuurregels escaped tonen.');
adminInvoiceAssert(strpos($html, 'name="issue_date" value="2026-09-21" ajax="onchange" action="correct-date-event"') !== false,
    'Definitief detail moet de factuurdatum via AJAX kunnen corrigeren.');
adminInvoiceAssert(substr_count($html, '<input') === 1, 'Definitief detail mag buiten de factuurdatum geen wijzigbare velden hebben.');
adminInvoiceAssert(strpos($html, '€ 121,00') !== false, 'Detail moet de vastgezette definitieve totalen tonen.');

echo "AdminInvoice detail template tests passed.\n";
