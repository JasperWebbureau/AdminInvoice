<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceRecord;
use Repository\Repository;

final class RecurringInvoiceRecordRepository extends Repository
{
    public function getEntity()
    {
        return new RecurringInvoiceRecord();
    }
}
