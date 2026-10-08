<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;

/** Optionele betaalmodule levert incassoaankondiging en activeert na geregistreerde verzending. */
interface InvoiceCollectionExtensionInterface
{
    public function prepare(Invoice $invoice): string;
    public function noticeSent(Invoice $invoice): void;
    public function processDue(string $today): array;
}
