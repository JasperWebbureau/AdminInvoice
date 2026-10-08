<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;

interface InvoicePaymentDetailPortInterface
{
    public function findPaymentDetailByPublicId(
        TenantId $tenantId,
        string $publicId
    ): ?InvoiceDetailContext;
}
