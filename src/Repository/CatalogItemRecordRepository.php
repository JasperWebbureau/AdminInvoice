<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Repository;

use Flexgrid\Modules\AdminInvoice\Entity\CatalogItemRecord;
use Repository\Repository;

final class CatalogItemRecordRepository extends Repository
{
    public function getEntity()
    {
        return new CatalogItemRecord();
    }
}
