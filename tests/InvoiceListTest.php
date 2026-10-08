<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceOverviewSummary;
use Flexgrid\Modules\AdminInvoice\Application\UseCase\ListInvoices;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceListRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceOverviewPresenter;
use Flexgrid\Utils\_Time;

final class MemoryInvoiceListRepository implements InvoiceListRepositoryInterface
{
    /** @var TenantId|null */ public $searchTenant;
    /** @var TenantId|null */ public $summaryTenant;
    /** @var TenantId|null */ public $yearsTenant;

    public function search(TenantId $tenantId, InvoiceListQuery $query): InvoiceListResult
    {
        $this->searchTenant = $tenantId;
        return new InvoiceListResult([
            new InvoiceListItem('invoice-1', '2026-0001', 'Klant B.V.', 'Project A', '2026-09-01', '2026-09-15', 12100, 'EUR', 'final', 'unpaid'),
            new InvoiceListItem('credit-1', 'CN-2026-0001', 'Klant B.V.', '', '2026-09-20', '', -12100, 'EUR', 'final', '', 'credit_note', '2026-0001'),
        ], 2, 1, 10);
    }

    public function getSummaries(TenantId $tenantId, ?int $year = null, ?int $quarter = null): array
    {
        $this->summaryTenant = $tenantId;
        return [new InvoiceOverviewSummary(1, 10000, 0, 12100, 'EUR')];
    }

    public function getAvailableYears(TenantId $tenantId): array
    {
        $this->yearsTenant = $tenantId;
        return [2026];
    }
}

$query = new InvoiceListQuery('Klant', 'final', 'unpaid', 2026, 'customer', 'asc', 1, 10);
adminInvoiceAssert((new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 10, '', 3))->getQuarter() === 3, 'Factuurquery moet kwartaal bewaren.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void { new InvoiceListQuery('', '', '', 2026, 'issue_date', 'desc', 1, 10, '', 5); }, 'Factuurquery moet ongeldig kwartaal weigeren.');
adminInvoiceAssert($query->getSearch() === 'Klant', 'Lijstquery moet zoekterm behouden.');
adminInvoiceAssert($query->getSort() === 'customer' && $query->getDirection() === 'asc', 'Lijstquery moet sortering valideren.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void {
    new InvoiceListQuery('', 'unknown');
}, 'Onbekende factuurstatus moet worden geweigerd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void {
    new InvoiceListQuery('', '', '', null, 'drop table');
}, 'Sorteerkolom buiten de whitelist moet worden geweigerd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void {
    new InvoiceListQuery('', '', '', null, 'issue_date', 'desc', 1, 11);
}, 'Niet-ondersteunde paginagrootte moet worden geweigerd.');
adminInvoiceAssertThrows(InvalidArgumentException::class, function (): void {
    new InvoiceListQuery('', '', '', null, 'issue_date', 'desc', 1, 10, 'offerte');
}, 'Onbekend documenttype moet worden geweigerd.');

$tenantId = new TenantId('list-tenant');
$repository = new MemoryInvoiceListRepository();
$data = (new ListInvoices(new TenantContext($tenantId), $repository))->execute($query);
adminInvoiceAssert($repository->searchTenant->equals($tenantId), 'Lijstlookup moet TenantContext gebruiken.');
adminInvoiceAssert($repository->summaryTenant->equals($tenantId), 'Samenvatting moet TenantContext gebruiken.');
adminInvoiceAssert($repository->yearsTenant->equals($tenantId), 'Jaaropties moeten TenantContext gebruiken.');

$viewModel = (new InvoiceOverviewPresenter(new _Time(strtotime('2026-09-20 12:00:00'))))->present(
    $data,
    'refresh-event',
    '/Flexgrid/AdminInvoice/edit',
    '/Flexgrid/AdminInvoice/detail',
    '/Flexgrid/AdminInvoice/creditNote'
);
adminInvoiceAssert(count($viewModel['summaryCards']) === 4, 'Presenter moet vier overzichtskaarten per valuta leveren.');
adminInvoiceAssert(count($viewModel['table']['rows']) === 2, 'Presenter moet facturen en creditnota’s naar tabelrijen vertalen.');
adminInvoiceAssert($viewModel['table']['rows'][0]['cells']['status']['value'] === 'Verlopen', 'Open factuur na vervaldatum moet als verlopen worden gepresenteerd.');
adminInvoiceAssert($viewModel['table']['rows'][0]['cells']['status']['badge'] === 'danger', 'Verlopen factuur moet danger-tone krijgen.');
adminInvoiceAssert($viewModel['table']['rows'][0]['cells']['gross_total'] === '€ 121,00', 'Presenter moet Money gebruiken voor bedragformattering.');
adminInvoiceAssert(
    $viewModel['table']['rows'][0]['url'] === '/Flexgrid/AdminInvoice/detail/invoice-1',
    'Definitieve facturen moeten vanuit het overzicht naar de read-only detailpagina linken.'
);
adminInvoiceAssert(
    $viewModel['table']['rows'][1]['url'] === '/Flexgrid/AdminInvoice/creditNote/credit-1',
    'Creditnota’s moeten vanuit hetzelfde overzicht naar hun read-only detailpagina linken.'
);
adminInvoiceAssert($viewModel['table']['rows'][1]['cells']['document_type']['value'] === 'Creditnota', 'Creditnota moet herkenbaar getypeerd zijn.');
adminInvoiceAssert($viewModel['table']['rows'][1]['cells']['invoice_number']['secondary'] === 'Bronfactuur 2026-0001', 'Creditnota moet de bronfactuur als secundaire informatie tonen.');
adminInvoiceAssert($viewModel['table']['rows'][1]['cells']['gross_total'] === '€ -121,00', 'Creditnota moet het negatieve bedrag tonen.');

echo "AdminInvoice list tests passed.\n";
