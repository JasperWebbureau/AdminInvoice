<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\InvoiceDispatchRecord;
use Repository\Repository;

final class InvoiceDispatchRecordRepository extends Repository
{
    public function getEntity()
    {
        return new InvoiceDispatchRecord();
    }
}
