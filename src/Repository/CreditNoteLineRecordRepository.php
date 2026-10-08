<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\CreditNoteLineRecord;
use Repository\Repository;

/** @see CreditNoteRecordRepository */
final class CreditNoteLineRecordRepository extends Repository
{
    public function getEntity()
    {
        return new CreditNoteLineRecord();
    }
}
