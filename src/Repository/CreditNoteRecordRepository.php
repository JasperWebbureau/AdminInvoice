<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\CreditNoteRecord;
use Repository\Repository;

/** Alleen Autowire-metadata; tenantqueries lopen via de expliciete PDO-adapter. */
final class CreditNoteRecordRepository extends Repository
{
    public function getEntity()
    {
        return new CreditNoteRecord();
    }
}
