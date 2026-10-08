<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;

interface CreditNoteRepositoryInterface
{
    public function insert(CreditNote $creditNote, int $createdAt): void;

    public function markOriginalInvoiceCredited(Invoice $invoice, int $updatedAt): void;

    public function findByPublicId(TenantId $tenantId, string $publicId): ?CreditNote;
}
