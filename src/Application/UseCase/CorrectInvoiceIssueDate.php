<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Utils\_Time;

final class CorrectInvoiceIssueDate
{
    private $tenantContext;
    private $transactions;
    private $invoices;
    private $clock;

    public function __construct(
        TenantContext $tenantContext,
        TransactionManagerInterface $transactions,
        InvoiceRepositoryInterface $invoices,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->transactions = $transactions;
        $this->invoices = $invoices;
        $this->clock = $clock;
    }

    public function execute(string $publicId, string $issueDate): Invoice
    {
        return $this->transactions->transactional(function () use ($publicId, $issueDate): Invoice {
            $invoice = $this->invoices->findByPublicIdForUpdate(
                $this->tenantContext->getTenantId(),
                trim($publicId)
            );
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }
            $unchanged = $invoice->getIssueDate()->getValue() === $issueDate;
            $invoice->correctIssueDate(new CalendarDate($issueDate));
            if ($unchanged) {
                return $invoice;
            }
            $this->invoices->correctIssueDate($invoice, (int)$this->clock->get());

            return $invoice;
        });
    }
}
