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

if (!function_exists('t')) {
    function t(string $key, string $fallback): string
    {
        return $fallback;
    }
}

$invoice = new Invoice(
    'template-invoice',
    new TenantId('template-tenant'),
    new Currency('EUR'),
    new PartySnapshot('<Klant>'),
    new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
    new CalendarDate('2026-09-18'),
    new CalendarDate('2026-10-18')
);
$invoice->addLine(new InvoiceLine(
    'template-line',
    '<Regel>',
    Quantity::fromDecimal('2'),
    'uur',
    Money::fromDecimal('10,00', $invoice->getCurrency()),
    TaxRate::fromPercentage('21'),
    new TaxCategory(TaxCategory::STANDARD),
    10
));
$viewModel = (new InvoiceEditorPresenter(new InvoiceCalculator()))->present($invoice, [
    'update' => 'invoice.update',
    'add_line' => 'invoice.add-line',
    'update_line' => 'invoice.update-line',
    'remove_line' => 'invoice.remove-line',
]);

extract($viewModel, EXTR_OVERWRITE);
$customerEditUrl='/Flexgrid/AdminCustomer/edit/customer-1';
$actions['refresh_customer']='invoice.refresh-customer';
$sourceQuoteUrl='/Flexgrid/AdminQuote/detail/quote-1';
ob_start();
include dirname(__DIR__) . '/src/Templates/Editor/Content.php';
$html = (string)ob_get_clean();
adminInvoiceAssert(strpos($html,'fas fa-pencil')!==false&&strpos($html,'action="invoice.refresh-customer"')!==false,'Conceptfactuur moet klantbewerking en expliciete snapshotvernieuwing tonen.');
adminInvoiceAssert(strpos($html,$sourceQuoteUrl)!==false,'Uit offerte gemaakte factuur moet naar bronofferte verwijzen.');

adminInvoiceAssert(strpos($html, 'data-admin-invoice-editor') === false, 'Vervangbare wrapper hoort in de buitenste editor-template.');
adminInvoiceAssert(strpos($html, 'action="invoice.update"') !== false, 'Headerformulier moet de update-AjaxEvent gebruiken.');
adminInvoiceAssert(strpos($html, 'action="invoice.add-line"') !== false, 'Regelformulier moet de add-line-AjaxEvent gebruiken.');
adminInvoiceAssert(strpos($html, 'action="invoice.update-line"') !== false, 'Bestaande regel moet de update-line-AjaxEvent gebruiken.');
adminInvoiceAssert(strpos($html, 'data-admin-invoice-line-field') !== false, 'Bestaande regel moet inline wijzigbare velden renderen.');
adminInvoiceAssert(strpos($html, '&lt;Klant&gt;') !== false, 'Snapshotwaarden moeten escaped worden.');
adminInvoiceAssert(strpos($html, '&lt;Regel&gt;') !== false, 'Inline regelwaarden moeten escaped worden.');
adminInvoiceAssert(strpos($html, '€ 24,20') !== false, 'Editor moet herberekende brutototalen tonen.');

$finalizeButton = (string)file_get_contents(
    dirname(__DIR__) . '/src/Templates/Editor/FinalizeButton.php'
);
ob_start();
$previewUrl = '/Flexgrid/AdminInvoice/previewPdf/template-invoice';
include dirname(__DIR__) . '/src/Templates/Editor/PreviewButton.php';
$previewButton = (string)ob_get_clean();
adminInvoiceAssert(
    strpos($previewButton, 'data-admin-invoice-preview') !== false
    && strpos($previewButton, 'target="_blank"') !== false
    && strpos($previewButton, $previewUrl) !== false,
    'Concept-PDF moet vanuit de editor in een nieuw tabblad te bekijken zijn.'
);
adminInvoiceAssert(
    strpos($finalizeButton, 'data-admin-invoice-finalize') !== false
    && strpos($finalizeButton, 'ajax="true"') !== false
    && strpos($finalizeButton, 'public_id=') !== false
    && strpos($finalizeButton, 'alert=') !== false,
    'Finalisatieknop moet de declaratieve Flexgrid AJAX- en bevestigingsconventie gebruiken.'
);

ob_start();
$content = '';
$deleteAction = 'invoice.delete-draft';
$publicId = 'template-invoice';
include dirname(__DIR__) . '/src/Templates/Editor/HeaderActions.php';
$editorHtml = (string)ob_get_clean();
adminInvoiceAssert(
    strpos($editorHtml, 'Concept verwijderen') !== false
    && strpos($editorHtml, 'action="invoice.delete-draft"') !== false
    && strpos($editorHtml, 'public_id="template-invoice"') !== false
    && strpos($editorHtml, 'ajax="true"') !== false
    && strpos($editorHtml, 'alert=') !== false,
    'Verwijderknop moet bevestiging en tenantgebonden AJAX-actie gebruiken.'
);

echo "AdminInvoice editor template tests passed.\n";
