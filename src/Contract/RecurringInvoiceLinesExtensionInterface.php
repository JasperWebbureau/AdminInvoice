<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCatalog;

/** Een project kan per toegewezen item de regels bepalen; null behoudt de vaste catalogusregel.
 *  Het plan bevat catalog_item_public_id van het huidige item en bij gedeelde schema's
 *  assigned_item_public_ids van alle actieve items op dezelfde klantkoppeling.
 */
interface RecurringInvoiceLinesExtensionInterface
{
    public function validate(array $plan, InvoiceCustomerSelection $customer, InvoiceCatalog $catalog): void;

    public function resolve(
        array $plan,
        InvoiceCustomerSelection $customer,
        InvoiceCatalog $catalog,
        string $issueDate
    ): ?array;
}
