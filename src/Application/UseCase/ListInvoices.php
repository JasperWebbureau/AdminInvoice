<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceListRepositoryInterface;

final class ListInvoices
{
    /** @var TenantContext */ private $tenantContext;
    /** @var InvoiceListRepositoryInterface */ private $invoices;

    public function __construct(TenantContext $tenantContext, InvoiceListRepositoryInterface $invoices)
    {
        $this->tenantContext = $tenantContext;
        $this->invoices = $invoices;
    }

    public function execute(InvoiceListQuery $query): array
    {
        $tenantId = $this->tenantContext->getTenantId();

        return [
            'query' => $query,
            'result' => $this->invoices->search($tenantId, $query),
            'summaries' => $this->invoices->getSummaries($tenantId, $query->getYear(), $query->getQuarter()),
            'years' => $this->invoices->getAvailableYears($tenantId),
        ];
    }
}
