<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;

interface InvoiceCustomerHistoryRepositoryInterface
{
    /** @return \Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem[] */
    public function findRecentByCustomer(TenantId $tenantId, string $customerPublicId, int $limit): array;
}
