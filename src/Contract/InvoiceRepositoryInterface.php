<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;

interface InvoiceRepositoryInterface
{
    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void;

    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice;

    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice;

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice;

    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void;

    public function correctIssueDate(Invoice $invoice, int $updatedAt): void;

    public function deleteDraft(Invoice $invoice): void;

    public function finalize(Invoice $invoice): void;
}
