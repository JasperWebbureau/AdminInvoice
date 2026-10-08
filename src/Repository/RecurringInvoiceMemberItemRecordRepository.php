<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\RecurringInvoiceMemberItemRecord;
use Repository\Repository;

final class RecurringInvoiceMemberItemRecordRepository extends Repository
{
    public function getEntity()
    {
        return new RecurringInvoiceMemberItemRecord();
    }
}
