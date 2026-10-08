<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;

interface InvoiceDetailExtensionInterface
{
    public function render(InvoiceDetailContext $context): string;
}
