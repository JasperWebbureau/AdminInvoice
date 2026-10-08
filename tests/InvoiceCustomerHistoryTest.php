<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TableRenderer.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\ListCustomerDocuments;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerHistoryRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Service\CustomerInvoiceHistoryPresenter;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceOverviewPresenter;
use Flexgrid\Utils\_Time;

final class MemoryInvoiceCustomerHistoryRepository implements InvoiceCustomerHistoryRepositoryInterface
{
    /** @var TenantId|null */ public $tenantId;
    /** @var string */ public $customerPublicId = '';
    /** @var int */ public $limit = 0;

    public function findRecentByCustomer(TenantId $tenantId, string $customerPublicId, int $limit): array
    {
        $this->tenantId = $tenantId;
        $this->customerPublicId = $customerPublicId;
        $this->limit = $limit;
        return [
            new InvoiceListItem('history-invoice', '', 'Klant B.V.', '', '2026-09-18', '2026-10-18', 12100, 'EUR', 'draft', 'unpaid'),
            new InvoiceListItem('history-credit', 'CN-2026-0001', 'Klant B.V.', '', '2026-09-17', '', -12100, 'EUR', 'final', '', 'credit_note', '2026-0001'),
        ];
    }
}

$tenantId = new TenantId('customer-history-tenant');
$repository = new MemoryInvoiceCustomerHistoryRepository();
$items = (new ListCustomerDocuments(new TenantContext($tenantId), $repository))->execute('customer-history-id', 5);
adminInvoiceAssert($repository->tenantId->equals($tenantId), 'Klantdocumentquery moet de actieve tenant gebruiken.');
adminInvoiceAssert($repository->customerPublicId === 'customer-history-id', 'Klantdocumentquery moet uitsluitend de publieke Customer-id gebruiken.');
adminInvoiceAssert($repository->limit === 5 && count($items) === 2, 'Klantdocumentquery moet een begrensde recente lijst opvragen.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($repository, $tenantId): void {
    (new ListCustomerDocuments(new TenantContext($tenantId), $repository))->execute('', 5);
}, 'Lege Customer-id moet worden geweigerd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function () use ($repository, $tenantId): void {
    (new ListCustomerDocuments(new TenantContext($tenantId), $repository))->execute('customer-history-id', 21);
}, 'Onbegrensde klanthistorie moet worden geweigerd.');

$table = (new CustomerInvoiceHistoryPresenter(
    new InvoiceOverviewPresenter(new _Time(strtotime('2026-09-20 12:00:00 UTC')))
))->present(
    $items,
    '/Flexgrid/AdminInvoice/edit',
    '/Flexgrid/AdminInvoice/detail',
    '/Flexgrid/AdminInvoice/creditNote'
);
adminInvoiceAssert(!empty($table['compact']), 'Klanthistorie moet de compacte TableRenderer-variant gebruiken.');
adminInvoiceAssert(count($table['rows']) === 2, 'Presenter moet facturen en creditnota’s in dezelfde historie opnemen.');
adminInvoiceAssert(count($table['columns']) === 5, 'Klantdetail moet alleen relevante documentkolommen tonen.');
adminInvoiceAssert($table['rows'][0]['url'] === '/Flexgrid/AdminInvoice/edit/history-invoice', 'Concept moet naar de factuureditor linken.');
adminInvoiceAssert($table['rows'][1]['url'] === '/Flexgrid/AdminInvoice/creditNote/history-credit', 'Creditnota moet naar het immutable detail linken.');

$html = (new \Flexgrid\Html\Table\TableRenderer($table))->render();
adminInvoiceAssert(strpos($html, 'data-fg-table="admin-customer-invoice-history"') !== false, 'Klanthistorie moet de gedeelde TableRenderer gebruiken.');
adminInvoiceAssert(strpos($html, 'CN-2026-0001') !== false, 'Klanthistorie moet documentnummers tonen.');

echo "AdminInvoice customer history tests passed.\n";
