<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class RemoveInvoiceLine
{
    /** @var TenantContext */
    private $tenantContext;
    /** @var TransactionManagerInterface */
    private $transactions;
    /** @var InvoiceRepositoryInterface */
    private $invoices;
    /** @var InvoiceCalculator */
    private $calculator;
    /** @var _Time */
    private $clock;

    public function __construct(
        TenantContext $tenantContext,
        TransactionManagerInterface $transactions,
        InvoiceRepositoryInterface $invoices,
        InvoiceCalculator $calculator,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->transactions = $transactions;
        $this->invoices = $invoices;
        $this->calculator = $calculator;
        $this->clock = $clock;
    }

    public function execute(string $invoicePublicId, string $linePublicId): Invoice
    {
        return $this->transactions->transactional(function () use ($invoicePublicId, $linePublicId): Invoice {
            $invoice = $this->invoices->findByPublicIdForUpdate(
                $this->tenantContext->getTenantId(),
                trim($invoicePublicId)
            );
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }

            $invoice->removeLine(trim($linePublicId));
            $this->invoices->updateDraft(
                $invoice,
                $this->calculator->calculate($invoice),
                (int)$this->clock->get()
            );

            return $invoice;
        });
    }
}
