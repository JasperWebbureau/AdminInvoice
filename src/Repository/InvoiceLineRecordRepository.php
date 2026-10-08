<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\InvoiceLineRecord;
use Repository\Repository;

/** @see InvoiceRecordRepository */
final class InvoiceLineRecordRepository extends Repository
{
    public function getEntity()
    {
        return new InvoiceLineRecord();
    }
}
