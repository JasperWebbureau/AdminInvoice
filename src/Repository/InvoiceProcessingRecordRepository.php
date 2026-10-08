<?php

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Repository\Repository;
use Flexgrid\Modules\AdminInvoice\Entity\InvoiceProcessingRecord;

class InvoiceProcessingRecordRepository extends Repository
{
    public function getEntity() { return new InvoiceProcessingRecord(); }
}
