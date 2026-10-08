<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceMemberRecord;
use Repository\Repository;

final class RecurringInvoiceMemberRecordRepository extends Repository
{
    public function getEntity()
    {
        return new RecurringInvoiceMemberRecord();
    }
}
