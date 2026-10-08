<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TableRenderer.php';

use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceOverviewSummary;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceOverviewPresenter;
use Flexgrid\Utils\_Time;

if (!function_exists('t')) {
    function t(string $key, string $fallback): string
    {
        return $fallback;
    }
}

$queryObject = new InvoiceListQuery('<zoek>', 'draft', '', 2026);
$viewModel = (new InvoiceOverviewPresenter(new _Time(1770000000)))->present([
    'query' => $queryObject,
    'result' => new InvoiceListResult([
        new InvoiceListItem('invoice-template', '', '<script>alert(1)</script>', '', '2026-09-18', '2026-10-18', 12100, 'EUR', 'draft', 'unpaid'),
        new InvoiceListItem('credit-template', 'CN-2026-0001', 'Veilige klant', '', '2026-09-19', '', -12100, 'EUR', 'final', '', 'credit_note', '2026-0001'),
    ], 2, 1, 10),
    'summaries' => [new InvoiceOverviewSummary(1, 0, 0, 0, 'EUR')],
    'years' => [2026],
], 'refresh-event', '/Flexgrid/AdminInvoice/edit', '/Flexgrid/AdminInvoice/detail', '/Flexgrid/AdminInvoice/creditNote', 'correct-date-event', 'delete-draft-event');

extract($viewModel, EXTR_OVERWRITE);
ob_start();
include dirname(__DIR__) . '/src/Templates/Invoices/Content.php';
$html = (string)ob_get_clean();

adminInvoiceAssert(strpos($html, 'data-admin-invoice-filters') !== false, 'Overzicht moet een stabiele AJAX-filtercontainer hebben.');
adminInvoiceAssert(strpos($html, 'action="refresh-event"') !== false, 'Filterformulier moet de refresh-eventnaam gebruiken.');
adminInvoiceAssert(strpos($html, 'data-fg-table="admin-invoice-overview"') !== false, 'Overzicht moet de generieke TableRenderer gebruiken.');
adminInvoiceAssert(strpos($html, '&lt;zoek&gt;') !== false, 'Zoekterm moet in het formulier escaped worden.');
adminInvoiceAssert(strpos($html, '<script>alert(1)</script>') === false, 'Klantnaam uit snapshot mag geen HTML injecteren.');
adminInvoiceAssert(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Klantnaam moet zichtbaar maar escaped blijven.');
adminInvoiceAssert(strpos($html, 'data-admin-invoice-page="1"') !== false, 'Overzicht moet AJAX-paginering renderen.');
adminInvoiceAssert(strpos($html, 'data-fg-table-row-url="/Flexgrid/AdminInvoice/edit/invoice-template"') !== false, 'Conceptregels moeten naar de editor linken.');
adminInvoiceAssert(strpos($html, 'name="document_type"') !== false, 'Overzicht moet op facturen en creditnota’s kunnen filteren.');
adminInvoiceAssert(strpos($html, 'data-fg-table-row-url="/Flexgrid/AdminInvoice/creditNote/credit-template"') !== false, 'Creditnotaregels moeten naar hun detail linken.');
adminInvoiceAssert(strpos($html, 'Bronfactuur 2026-0001') !== false, 'Creditnotarij moet de bronfactuur tonen.');
adminInvoiceAssert(strpos($html, 'name="issue_date" value="2026-09-18" ajax="onchange" action="correct-date-event"') !== false,
    'Factuurrijen moeten de datum inline via AJAX kunnen wijzigen.');
adminInvoiceAssert(strpos($html, 'action="delete-draft-event"') !== false
    && strpos($html, 'data-public-id="invoice-template"') !== false,
    'Alleen de conceptfactuur moet een verwijderactie in de tabel krijgen.');
adminInvoiceAssert(substr_count($html, 'name="issue_date"') === 1,
    'Creditnotadatum mag niet als wijzigbare factuurdatum verschijnen.');

$createUrl = '/Flexgrid/AdminInvoice/create?customer=1&source=test';
ob_start();
include dirname(__DIR__) . '/src/Templates/Invoices/HeaderActions.php';
$headerHtml = (string)ob_get_clean();
adminInvoiceAssert(strpos($headerHtml, 'Concepten controleren') !== false
    && strpos($headerHtml, 'Factuuritems') !== false
    && strpos($headerHtml, 'Terugkerende facturen') !== false
    && strpos($headerHtml, 'Nieuwe factuur') !== false,
    'Alle factuuroverzichtacties moeten in de main-header beschikbaar blijven.');
adminInvoiceAssert(strpos($headerHtml, 'customer=1&amp;source=test') !== false,
    'De aanmaak-URL in de main-header moet escaped worden.');

echo "AdminInvoice overview template tests passed.\n";
