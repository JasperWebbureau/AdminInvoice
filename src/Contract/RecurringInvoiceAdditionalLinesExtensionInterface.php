<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCatalog;

/** Optionele aanvulling op RecurringInvoiceLinesExtensionInterface, na alle toegewezen items.
 *  Retourneer [] voor geen extra regels, of ['currency' => 'EUR', 'lines' => [...]].
 *  Zo kan een project meetbare kosten toevoegen zonder per klant een item toe te wijzen.
 */
interface RecurringInvoiceAdditionalLinesExtensionInterface
{
    public function additionalLines(
        array $plan,
        InvoiceCustomerSelection $customer,
        InvoiceCatalog $catalog,
        string $issueDate,
        array $assignedItemPublicIds,
        array $existingLines
    ): array;
}
