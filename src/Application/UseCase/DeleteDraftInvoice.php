<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;

final class DeleteDraftInvoice
{
    /** @var TenantContext */
    private $tenantContext;
    /** @var TransactionManagerInterface */
    private $transactions;
    /** @var InvoiceRepositoryInterface */
    private $invoices;

    public function __construct(
        TenantContext $tenantContext,
        TransactionManagerInterface $transactions,
        InvoiceRepositoryInterface $invoices
    ) {
        $this->tenantContext = $tenantContext;
        $this->transactions = $transactions;
        $this->invoices = $invoices;
    }

    public function execute(string $publicId): void
    {
        $this->transactions->transactional(function () use ($publicId): void {
            $invoice = $this->invoices->findByPublicIdForUpdate(
                $this->tenantContext->getTenantId(),
                trim($publicId)
            );
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }
            if ($invoice->getStatus()->getValue() !== InvoiceStatus::DRAFT) {
                throw new \DomainException('Alleen een conceptfactuur mag worden verwijderd.');
            }

            $this->invoices->deleteDraft($invoice);
        });
    }
}
