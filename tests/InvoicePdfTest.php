<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\GenerateInvoicePdf;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfTemplateSelector;
use Flexgrid\Modules\AdminInvoice\Service\InvoicePdfViewModelFactory;
use Flexgrid\Modules\AdminInvoice\Service\ProjectInvoiceBranding;
use Flexgrid\Modules\AdminInvoice\Service\ScssBrandColorReader;
use Flexgrid\Modules\AdminPdf\Infrastructure\DompdfDocumentRenderer;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class InvoiceSafePreviewRenderer implements DocumentRendererInterface
{
    public $template = '';
    public function render(string $template, array $viewModel, PdfRenderOptions $options, string $fileName): PdfDocument
    {
        $this->template = $template;
        return new PdfDocument('%PDF-safe-preview', $fileName, 1);
    }
}

$projectRoot = dirname(__DIR__, 4);
$projectTemplates = $projectRoot . '/Templates/AdminInvoice/Pdf';
$moduleTemplates = dirname(__DIR__) . '/src/Templates/Pdf';
$resolver = new FilesystemTemplateResolver(is_dir($projectTemplates)
    ? [$projectTemplates, $moduleTemplates] : [$moduleTemplates]);
$selector = new InvoicePdfTemplateSelector($resolver);

adminInvoiceAssert($selector->select('default') === 'default.php', 'Standaard PDF-template moet beschikbaar zijn.');
adminInvoiceAssert($selector->select('bestaat-niet') === 'default.php', 'Ontbrekende projecttemplate moet veilig terugvallen.');
adminInvoiceAssert($selector->select('../default') === 'default.php', 'Ongeldige templatenaam mag geen padinjectie toelaten.');

$colors = (new ScssBrandColorReader())->read(
    $projectRoot . '/Files/Assets/Css/Variables.scss',
    $projectRoot . '/flexgrid/flexgrid/src/Assets/Css/Variables.scss'
);
adminInvoiceAssert($colors['primary'] === '#A38668', 'Primaire PDF-kleur moet uit de project-Variables.scss komen.');
adminInvoiceAssert($colors['secondary'] === '#A38668', 'Secundaire PDF-kleur moet uit de project-Variables.scss komen.');

$branding = (new ProjectInvoiceBranding(__DIR__ . '/Fixtures', new ScssBrandColorReader()))->create([
    'name' => 'Voorbeeldbedrijf B.V.',
    'street' => 'Ondernemersweg 10',
    'postal' => '1234 AB',
    'city' => 'Utrecht',
    'country' => 'NL',
    'vat_nr' => 'NL123456789B01',
    'kvk' => '12345678',
    'iban' => 'NL00BANK0123456789',
    'iban_holder' => 'Voorbeeldbedrijf B.V.',
    'email' => 'administratie@example.test',
    'tel' => '030-1234567',
    'domain' => 'example.test',
], __DIR__ . '/Fixtures/Files/logo.svg');
adminInvoiceAssert(strpos($branding['company']['logo_data_uri'], 'data:image/svg+xml;base64,') === 0, 'Lokaal logo moet zonder remote resource als data-URI beschikbaar zijn.');

$currency = Currency::euro();
$invoice = new Invoice(
    'pdf-invoice',
    new TenantId('pdf-tenant'),
    $currency,
    new PartySnapshot('Klant & Partners B.V.', 'Mevrouw De Vries', 'inkoop@example.test'),
    new AddressSnapshot('Klantstraat 2', '5678 CD', 'Rotterdam', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18'),
    'INK-2026-42',
    'Bedankt voor de prettige samenwerking.'
);
$invoice->addLine(new InvoiceLine(
    'pdf-line-1',
    'Ontwerp en ontwikkeling',
    Quantity::fromDecimal('12.5'),
    'uur',
    Money::fromDecimal('85.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));
$invoice->addLine(new InvoiceLine(
    'pdf-line-2',
    'Hosting en onderhoud',
    Quantity::fromDecimal('1'),
    'jaar',
    Money::fromDecimal('240.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    20
));
$calculator = new InvoiceCalculator();
$invoice->finalize('2026-0042', 42, 1770004321, $calculator->calculate($invoice));

$viewModel = (new InvoicePdfViewModelFactory())->create($invoice, $branding);
$assertScalars = static function ($value) use (&$assertScalars): void {
    adminInvoiceAssert(is_array($value) || is_scalar($value) || $value === null, 'PDF-viewmodel mag alleen arrays en scalars bevatten.');
    if (is_array($value)) {
        foreach ($value as $item) {
            $assertScalars($item);
        }
    }
};
$assertScalars($viewModel);
adminInvoiceAssert($viewModel['invoice']['gross_total'] === '€ 1.576,03', 'PDF moet de definitieve totalensnapshot formatteren.');
adminInvoiceAssert($viewModel['company']['kvk'] === '12345678', 'PDF-viewmodel moet algemene bedrijfsgegevens ontvangen.');

$generator = new GenerateInvoicePdf(
    new DompdfDocumentRenderer($resolver),
    $selector,
    new InvoicePdfViewModelFactory(),
    new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', is_dir($projectTemplates)
        ? [$projectTemplates, $moduleTemplates] : [$moduleTemplates]),
    'default',
    $branding
);
$document = $generator->execute($invoice);
adminInvoiceAssert(strncmp($document->getContent(), '%PDF-', 5) === 0, 'On-demand factuurgeneratie moet echte PDF-bytes opleveren.');
adminInvoiceAssert($document->getFileName() === 'factuur-2026-0042.pdf', 'PDF-bestandsnaam moet het veilige factuurnummer bevatten.');
adminInvoiceAssert($document->getPageCount() >= 1, 'Factuur-PDF moet minimaal één pagina bevatten.');

$draft = new Invoice(
    'draft-pdf',
    new TenantId('pdf-tenant'),
    $currency,
    new PartySnapshot('Conceptklant'),
    new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18')
);
$draft->addLine(new InvoiceLine(
    'draft-line',
    'Controleer deze post',
    Quantity::fromDecimal('2'),
    'stuk',
    Money::fromDecimal('10.00', $currency),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));
adminInvoiceAssertThrows(DomainException::class, function () use ($generator, $draft): void {
    $generator->execute($draft);
}, 'Een conceptfactuur mag niet via de definitieve PDF-route worden gegenereerd.');

$draftViewModel = (new InvoicePdfViewModelFactory())->createPreview($draft, $branding);
$assertScalars($draftViewModel);
adminInvoiceAssert($draftViewModel['invoice']['is_preview'] === true, 'Concept-PDF moet als preview zijn gemarkeerd.');
adminInvoiceAssert($draftViewModel['invoice']['number'] === '', 'Concept-PDF mag geen officieel factuurnummer tonen.');
adminInvoiceAssert($draftViewModel['invoice']['lines'][0]['description'] === 'Controleer deze post', 'Concept-PDF moet de opgeslagen regels tonen.');
adminInvoiceAssert($draftViewModel['invoice']['gross_total'] !== '', 'Concept-PDF moet actuele totalen berekenen.');
$previewDocument = $generator->preview($draft);
adminInvoiceAssert(strncmp($previewDocument->getContent(), '%PDF-', 5) === 0, 'Conceptpreview moet echte PDF-bytes opleveren.');
adminInvoiceAssert($previewDocument->getFileName() === 'conceptfactuur-draft-pdf.pdf', 'Conceptpreview moet een herkenbare bestandsnaam krijgen.');
adminInvoiceAssert($draft->getStatus()->getValue() === 'draft' && $draft->getInvoiceNumber() === null && $draft->getFinalizedTotals() === null, 'Een PDF-preview mag de factuur niet definitief maken of nummeren.');
$safeRenderer = new InvoiceSafePreviewRenderer();
$safeGenerator = new GenerateInvoicePdf(
    new DompdfDocumentRenderer($resolver), $selector, new InvoicePdfViewModelFactory(),
    new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', [$moduleTemplates]),
    'project-template', $branding, $safeRenderer
);
$safeGenerator->preview($draft);
adminInvoiceAssert($safeRenderer->template === 'default.php',
    'Een projectoverride mag de verplichte conceptmarkering niet vervangen.');
adminInvoiceAssertThrows(DomainException::class, function () use ($generator, $invoice): void {
    $generator->preview($invoice);
}, 'De conceptpreview mag geen definitieve factuur verwerken.');

$optionalPdfInvoice = new Invoice('optional-pdf', new TenantId('pdf-tenant'), Currency::euro(), new PartySnapshot('Klant zonder adres of contact'), new AddressSnapshot('', '', '', ''), new CalendarDate('2026-09-18'), new CalendarDate('2026-10-18'));
adminInvoiceAssert(strncmp($generator->preview($optionalPdfInvoice)->getContent(), '%PDF-', 5) === 0, 'PDF moet zonder klantadres en contactpersoon kunnen worden gemaakt.');

extract($draftViewModel, EXTR_OVERWRITE);
ob_start();
include $moduleTemplates . '/default.php';
$previewHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($previewHtml, 'CONCEPT') !== false && strpos($previewHtml, 'Niet betalen') !== false, 'Concept-PDF moet duidelijk als niet-betaalbaar zijn gemarkeerd.');
adminInvoiceAssert(strpos($previewHtml, 'Nog geen factuurnummer') !== false, 'Concept-PDF moet duidelijk maken dat er nog geen nummer is.');
adminInvoiceAssert(strpos($previewHtml, 'Gelieve het totaal') === false, 'Concept-PDF mag geen betaalverzoek tonen.');
adminInvoiceAssert(strpos($previewHtml, $branding['company']['iban']) === false, 'Concept-PDF mag geen IBAN tonen.');

$templateSource = (string)file_get_contents($moduleTemplates . '/default.php');
foreach (['Settings::', 'Connection::', 'Repository', 'SELECT ', '$_REQUEST'] as $forbidden) {
    adminInvoiceAssert(strpos($templateSource, $forbidden) === false, 'PDF-template bevat verboden logica: ' . $forbidden);
}

$settingsSource = (string)file_get_contents($projectRoot . '/flexgrid/flexgrid/src/App/Settings/Default/DefaultSettings.php');
adminInvoiceAssert(strpos($settingsSource, 'administration_invoice_pdf_template') !== false, 'Templatekeuze moet in de algemene settings staan.');

echo "AdminInvoice PDF tests passed.\n";
