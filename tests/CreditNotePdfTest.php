<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateCreditNotePdf;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\CreditNotePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminPdf\Infrastructure\DompdfDocumentRenderer;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

$moduleTemplates = dirname(__DIR__) . '/src/Templates/Pdf';
$resolver = new FilesystemTemplateResolver([$moduleTemplates]);
$currency = Currency::euro();
$line = new InvoiceLine(
    'credit-pdf-line',
    'Volledige correctie',
    Quantity::fromDecimal('2'),
    'uur',
    Money::fromDecimal('-50.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
);
$creditNote = new CreditNote(
    'credit-pdf-note',
    new TenantId('credit-pdf-tenant'),
    'CN-2026-0042',
    42,
    'credit-pdf-invoice',
    '2026-0042',
    $currency,
    new PartySnapshot('Creditklant B.V.', 'Inkoop', 'credit@example.test'),
    new AddressSnapshot('Markt 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    'Opdracht volledig geannuleerd.',
    [$line],
    (new InvoiceCalculator())->calculateLines($currency, [$line]),
    1770004321
);
$branding = [
    'company' => ['name' => 'Voorbeeldbedrijf B.V.', 'kvk' => '12345678'],
    'brand' => ['primary' => '#05043E', 'secondary' => '#722FBE'],
];
$viewModel = (new CreditNotePdfViewModelFactory())->create($creditNote, $branding);
adminInvoiceAssert($viewModel['invoice']['document_type'] === 'credit_note', 'PDF-viewmodel moet de template als creditnota laten renderen.');
adminInvoiceAssert($viewModel['invoice']['original_invoice_number'] === '2026-0042', 'Creditnota-PDF moet het bronfactuurnummer bevatten.');
adminInvoiceAssert($viewModel['invoice']['gross_total'] === '€ -121,00', 'Creditnota-PDF moet het negatieve totaal formatteren.');

$generator = new GenerateCreditNotePdf(
    new DompdfDocumentRenderer($resolver),
    new InvoicePdfTemplateSelector($resolver),
    new CreditNotePdfViewModelFactory(),
    new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', [$moduleTemplates]),
    'default',
    $branding
);
$document = $generator->execute($creditNote);
adminInvoiceAssert(strncmp($document->getContent(), '%PDF-', 5) === 0, 'On-demand creditnotageneratie moet echte PDF-bytes opleveren.');
adminInvoiceAssert($document->getFileName() === 'creditnota-CN-2026-0042.pdf', 'Creditnota-PDF moet een veilige herkenbare bestandsnaam krijgen.');
adminInvoiceAssert($document->getPageCount() >= 1, 'Creditnota-PDF moet minimaal één pagina bevatten.');

$templateSource = (string)file_get_contents($moduleTemplates . '/default.php');
adminInvoiceAssert(strpos($templateSource, "document_type") !== false, 'De gedeelde standaardtemplate moet factuur en creditnota onderscheiden.');
adminInvoiceAssert(strpos($templateSource, 'Deze creditnota corrigeert factuur') !== false, 'Creditnota-PDF mag geen betalingsverzoek tonen.');

$settingsSource = (string)file_get_contents(dirname(__DIR__, 3) . '/flexgrid/src/App/Settings/Default/DefaultSettings.php');
adminInvoiceAssert(strpos($settingsSource, 'administration_credit_note_pdf_template') !== false, 'Creditnota-template moet afzonderlijk configureerbaar zijn.');

echo "AdminInvoice credit note PDF tests passed.\n";
