<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\PayableInvoice;

interface InvoicePaymentPortInterface
{
    public function findPayableByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?PayableInvoice;

    public function synchronizePaymentStatus(
        PayableInvoice $invoice,
        Money $allocatedTotal,
        int $updatedAt
    ): string;
}
