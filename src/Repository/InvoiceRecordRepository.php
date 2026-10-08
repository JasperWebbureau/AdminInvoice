<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\InvoiceRecord;
use Repository\Repository;

/**
 * Alleen Autowire-metadata/hydration. Tenantgebonden queries komen in een
 * expliciete InvoiceRepository-adapter; deze class biedt bewust geen finders.
 */
final class InvoiceRecordRepository extends Repository
{
    public function getEntity()
    {
        return new InvoiceRecord();
    }
}
