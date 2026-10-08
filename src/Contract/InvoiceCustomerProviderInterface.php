<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;

interface InvoiceCustomerProviderInterface
{
    /** @return InvoiceCustomerSelection[] */
    public function search(string $query, int $limit = 10): array;
    public function find(string $publicId): ?InvoiceCustomerSelection;
}
