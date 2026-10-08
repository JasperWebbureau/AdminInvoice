<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\NumberSequenceInterface;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminInvoice\Contract\CreditNoteRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Exception\InvoiceNotFoundException;
use Flexgrid\Modules\AdminInvoice\Service\CreditNoteNumberFormatter;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Utils\_Time;

final class CreateFullCreditNote
{
    private const SEQUENCE_KEY = 'credit_note';

    /** @var TenantContext */ private $tenantContext;
    /** @var PublicIdGeneratorInterface */ private $publicIds;
    /** @var TransactionManagerInterface */ private $transactions;
    /** @var NumberSequenceInterface */ private $sequences;
    /** @var InvoiceRepositoryInterface */ private $invoices;
    /** @var CreditNoteRepositoryInterface */ private $creditNotes;
    /** @var InvoiceCalculator */ private $calculator;
    /** @var CreditNoteNumberFormatter */ private $formatter;
    /** @var _Time */ private $clock;

    public function __construct(
        TenantContext $tenantContext,
        PublicIdGeneratorInterface $publicIds,
        TransactionManagerInterface $transactions,
        NumberSequenceInterface $sequences,
        InvoiceRepositoryInterface $invoices,
        CreditNoteRepositoryInterface $creditNotes,
        InvoiceCalculator $calculator,
        CreditNoteNumberFormatter $formatter,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->publicIds = $publicIds;
        $this->transactions = $transactions;
        $this->sequences = $sequences;
        $this->invoices = $invoices;
        $this->creditNotes = $creditNotes;
        $this->calculator = $calculator;
        $this->formatter = $formatter;
        $this->clock = $clock;
    }

    public function execute(string $invoicePublicId, string $reason): CreditNote
    {
        $invoicePublicId = trim($invoicePublicId);
        $reason = trim($reason);
        if ($invoicePublicId === '' || strlen($invoicePublicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }
        if ($reason === '' || strlen($reason) > 1000) {
            throw new \InvalidArgumentException('Reden voor de creditnota is verplicht en maximaal 1000 tekens.');
        }

        return $this->transactions->transactional(function () use ($invoicePublicId, $reason): CreditNote {
            $tenantId = $this->tenantContext->getTenantId();
            $invoice = $this->invoices->findByPublicIdForUpdate($tenantId, $invoicePublicId);
            if ($invoice === null) {
                throw new InvoiceNotFoundException('Factuur niet gevonden.');
            }
            if ($invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED) {
                throw new \DomainException('Alleen een definitieve, nog niet gecrediteerde factuur kan volledig worden gecrediteerd.');
            }
            if ($invoice->getInvoiceNumber() === null || $invoice->getFinalizedTotals() === null) {
                throw new \UnexpectedValueException('Definitieve factuur mist zijn finalisatiesnapshot.');
            }

            $timestamp = (int)$this->clock->get();
            $issueDate = new CalendarDate(gmdate('Y-m-d', $timestamp));
            if ($issueDate->isBefore($invoice->getIssueDate())) {
                throw new \DomainException('Creditnotadatum mag niet voor de factuurdatum liggen.');
            }
            $periodKey = substr($issueDate->getValue(), 0, 4);
            $sequenceValue = $this->sequences->reserve($tenantId, self::SEQUENCE_KEY, $periodKey);
            $lines = $this->creditLines($invoice);
            $totals = $this->calculator->calculateLines($invoice->getCurrency(), $lines);
            $originalTotals = $invoice->getFinalizedTotals();
            if (!$totals->getNet()->equals($originalTotals->getNet()->negate())
                || !$totals->getTax()->equals($originalTotals->getTax()->negate())
                || !$totals->getGross()->equals($originalTotals->getGross()->negate())
            ) {
                throw new \UnexpectedValueException('Creditnotatotalen spiegelen de definitieve factuursnapshot niet exact.');
            }
            $creditNote = new CreditNote(
                $this->publicIds->generate(),
                $tenantId,
                $this->formatter->format($periodKey, $sequenceValue),
                $sequenceValue,
                $invoice->getPublicId(),
                (string)$invoice->getInvoiceNumber(),
                $invoice->getCurrency(),
                $invoice->getCustomerSnapshot(),
                $invoice->getBillingAddressSnapshot(),
                $issueDate,
                $reason,
                $lines,
                $totals,
                $timestamp
            );

            $this->creditNotes->insert($creditNote, $timestamp);
            $invoice->markCredited();
            $this->creditNotes->markOriginalInvoiceCredited($invoice, $timestamp);

            return $creditNote;
        });
    }

    /** @return InvoiceLine[] */
    private function creditLines(Invoice $invoice): array
    {
        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $lines[] = new InvoiceLine(
                $this->publicIds->generate(),
                $line->getDescription(),
                $line->getQuantity(),
                $line->getUnit(),
                $line->getUnitPrice()->negate(),
                $line->getTaxRate(),
                $line->getTaxCategory(),
                $line->getPosition()
            );
        }

        return $lines;
    }
}
