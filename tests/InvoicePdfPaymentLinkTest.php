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
use Flexgrid\Modules\AdminPdf\Contract\DocumentRendererInterface;
use Flexgrid\Modules\AdminPdf\Infrastructure\FilesystemTemplateResolver;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfDocument;
use Flexgrid\Modules\AdminPdf\ValueObject\PdfRenderOptions;

final class PaymentLinkCaptureRenderer implements DocumentRendererInterface
{
    public $viewModel = [];
    public function render(string $template, array $viewModel, PdfRenderOptions $options, string $fileName): PdfDocument
    {
        $this->viewModel = $viewModel;
        return new PdfDocument('%PDF-test', $fileName, 1);
    }
}

$currency = Currency::euro();
$invoice = new Invoice('pdf-link', new TenantId('tenant'), $currency,
    new PartySnapshot('Klant'), new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-10-01'), new CalendarDate('2026-10-15'));
$invoice->addLine(new InvoiceLine('line', 'Dienst', Quantity::fromDecimal('1'), 'stuk',
    Money::fromDecimal('10.00', $currency), TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD), 10));
$invoice->finalize('2026-0001', 1, time(), (new InvoiceCalculator())->calculate($invoice));
$renderer = new PaymentLinkCaptureRenderer();
$calls = 0;
$templates = new InvoicePdfTemplateSelector(new FilesystemTemplateResolver([
    dirname(__DIR__) . '/src/Templates/Pdf',
]));
$generator = new GenerateInvoicePdf($renderer, $templates, new InvoicePdfViewModelFactory(),
    new PdfRenderOptions('A4', 'portrait', 'DejaVu Sans', []), 'default', [], $renderer,
    static function (Invoice $givenInvoice) use (&$calls, $invoice): array {
        $calls++;
        adminInvoiceAssert($givenInvoice === $invoice, 'De provider krijgt de definitieve factuur.');
        return ['payment_url' => 'https://mollie.example/pay', 'payment_qr_data_uri' => 'data:image/png;base64,dGVzdA=='];
    });
$generator->execute($invoice);
adminInvoiceAssert($calls === 1 && $renderer->viewModel['invoice']['payment_url'] === 'https://mollie.example/pay',
    'Definitieve PDF moet betaalgegevens uit de optionele provider ontvangen.');

$draft = new Invoice('draft-link', new TenantId('tenant'), $currency,
    new PartySnapshot('Klant'), new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-10-01'), new CalendarDate('2026-10-15'));
$generator->preview($draft);
adminInvoiceAssert($calls === 1 && !isset($renderer->viewModel['invoice']['payment_url']),
    'Een concept-PDF mag de provider niet aanroepen of een betaallink tonen.');

echo "AdminInvoice PDF payment link tests passed.\n";
