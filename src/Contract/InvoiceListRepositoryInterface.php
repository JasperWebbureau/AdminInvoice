<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceOverviewSummary;

interface InvoiceListRepositoryInterface
{
    public function search(TenantId $tenantId, InvoiceListQuery $query): InvoiceListResult;

    /** @return InvoiceOverviewSummary[] */
    public function getSummaries(TenantId $tenantId, ?int $year = null, ?int $quarter = null): array;

    /** @return int[] */
    public function getAvailableYears(TenantId $tenantId): array;
}
