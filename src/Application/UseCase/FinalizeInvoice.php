<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\NumberSequenceInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceNumberFormatter;
use Flexgrid\Utils\_Time;

final class FinalizeInvoice
{
    private const SEQUENCE_KEY = 'invoice';

    /** @var TenantContext */ private $tenantContext;
    /** @var TransactionManagerInterface */ private $transactions;
    /** @var NumberSequenceInterface */ private $sequences;
    /** @var InvoiceRepositoryInterface */ private $invoices;
    /** @var InvoiceCalculator */ private $calculator;
    /** @var InvoiceNumberFormatter */ private $formatter;
    /** @var _Time */ private $clock;

    public function __construct(
        TenantContext $tenantContext,
        TransactionManagerInterface $transactions,
        NumberSequenceInterface $sequences,
        InvoiceRepositoryInterface $invoices,
        InvoiceCalculator $calculator,
        InvoiceNumberFormatter $formatter,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->transactions = $transactions;
        $this->sequences = $sequences;
        $this->invoices = $invoices;
        $this->calculator = $calculator;
        $this->formatter = $formatter;
        $this->clock = $clock;
    }

    public function execute(string $publicId): Invoice
    {
        return $this->transactions->transactional(function () use ($publicId): Invoice {
            $tenantId = $this->tenantContext->getTenantId();
            $invoice = $this->invoices->findByPublicIdForUpdate($tenantId, $this->requiredId($publicId));
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }

            $periodKey = substr($invoice->getIssueDate()->getValue(), 0, 4);
            $sequenceValue = $this->sequences->reserve($tenantId, self::SEQUENCE_KEY, $periodKey);
            $totals = $this->calculator->calculate($invoice);
            $invoice->finalize(
                $this->formatter->format($periodKey, $sequenceValue),
                $sequenceValue,
                (int)$this->clock->get(),
                $totals
            );
            $this->invoices->finalize($invoice);

            return $invoice;
        });
    }

    private function requiredId(string $publicId): string
    {
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }

        return $publicId;
    }
}
