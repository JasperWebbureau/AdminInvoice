<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\Model;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

final class Invoice
{
    /** @var string */
    private $publicId;
    /** @var TenantId */
    private $tenantId;
    /** @var InvoiceStatus */
    private $status;
    /** @var PaymentStatus */
    private $paymentStatus;
    /** @var Currency */
    private $currency;
    /** @var PartySnapshot */
    private $customerSnapshot;
    /** @var AddressSnapshot */
    private $billingAddressSnapshot;
    /** @var CalendarDate */
    private $issueDate;
    /** @var CalendarDate */
    private $dueDate;
    /** @var string */
    private $customerReference;
    /** @var string */
    private $notes;
    /** @var string */
    private $source;
    /** @var string */
    private $externalId;
    /** @var InvoiceLine[] */
    private $lines = [];
    /** @var string|null */
    private $invoiceNumber;
    /** @var int|null */
    private $invoiceSequenceValue;
    /** @var int|null */
    private $finalizedAt;
    /** @var InvoiceTotals|null */
    private $finalizedTotals;

    public function __construct(
        string $publicId,
        TenantId $tenantId,
        Currency $currency,
        PartySnapshot $customerSnapshot,
        AddressSnapshot $billingAddressSnapshot,
        CalendarDate $issueDate,
        CalendarDate $dueDate,
        string $customerReference = '',
        string $notes = '',
        string $source = '',
        string $externalId = ''
    ) {
        $publicId = trim($publicId);
        $customerReference = trim($customerReference);
        $notes = trim($notes);
        $source = trim($source);
        $externalId = trim($externalId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Factuur vereist een geldige publieke id.');
        }
        if ($dueDate->isBefore($issueDate)) {
            throw new \InvalidArgumentException('Vervaldatum mag niet voor de factuurdatum liggen.');
        }
        if (($source === '') !== ($externalId === '')) {
            throw new \InvalidArgumentException('Bron en externe id moeten samen worden opgegeven.');
        }
        if (strlen($customerReference) > 128 || strlen($source) > 64 || strlen($externalId) > 128) {
            throw new \InvalidArgumentException('Factuurreferentie overschrijdt de maximale opslaglengte.');
        }

        $this->publicId = $publicId;
        $this->tenantId = $tenantId;
        $this->status = new InvoiceStatus(InvoiceStatus::DRAFT);
        $this->paymentStatus = new PaymentStatus(PaymentStatus::UNPAID);
        $this->currency = $currency;
        $this->customerSnapshot = $customerSnapshot;
        $this->billingAddressSnapshot = $billingAddressSnapshot;
        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->customerReference = $customerReference;
        $this->notes = $notes;
        $this->source = $source;
        $this->externalId = $externalId;
    }

    /**
     * Persistence factory. Nieuwe facturen horen altijd via de normale constructor
     * en application use-case te worden gemaakt.
     *
     * @param InvoiceLine[] $lines
     */
    public static function reconstitute(
        string $publicId,
        TenantId $tenantId,
        Currency $currency,
        PartySnapshot $customerSnapshot,
        AddressSnapshot $billingAddressSnapshot,
        CalendarDate $issueDate,
        CalendarDate $dueDate,
        InvoiceStatus $status,
        PaymentStatus $paymentStatus,
        array $lines,
        string $customerReference,
        string $notes,
        string $source,
        string $externalId,
        ?string $invoiceNumber,
        ?int $invoiceSequenceValue,
        ?int $finalizedAt,
        ?InvoiceTotals $finalizedTotals
    ): Invoice {
        $invoice = new self(
            $publicId,
            $tenantId,
            $currency,
            $customerSnapshot,
            $billingAddressSnapshot,
            $issueDate,
            $dueDate,
            $customerReference,
            $notes,
            $source,
            $externalId
        );
        foreach ($lines as $line) {
            if (!$line instanceof InvoiceLine) {
                throw new \InvalidArgumentException('Ongeldige opgeslagen factuurregel.');
            }
            $invoice->addLine($line);
        }

        $statusValue = $status->getValue();
        if ($statusValue === InvoiceStatus::FINALIZED || $statusValue === InvoiceStatus::CREDITED) {
            if ($invoiceNumber === null || $invoiceSequenceValue === null || $finalizedAt === null || $finalizedTotals === null) {
                throw new \UnexpectedValueException('Definitieve factuur mist finalisatiesnapshot.');
            }
            $invoice->finalize($invoiceNumber, $invoiceSequenceValue, $finalizedAt, $finalizedTotals);
            if ($statusValue === InvoiceStatus::CREDITED) {
                $invoice->status = new InvoiceStatus(InvoiceStatus::CREDITED);
            }
        } elseif ($statusValue === InvoiceStatus::CANCELLED) {
            $invoice->cancelDraft();
        } elseif ($invoiceNumber !== null || $invoiceSequenceValue !== null || $finalizedAt !== null || $finalizedTotals !== null) {
            throw new \UnexpectedValueException('Conceptfactuur bevat onverwachte finalisatiegegevens.');
        }

        $invoice->paymentStatus = $paymentStatus;

        return $invoice;
    }

    public function addLine(InvoiceLine $line): void
    {
        $this->assertDraft();
        $this->assertLineCurrency($line);
        if (isset($this->lines[$line->getPublicId()])) {
            throw new \DomainException('Factuurregel met deze publieke id bestaat al.');
        }
        $this->assertPositionAvailable($line);
        $this->lines[$line->getPublicId()] = $line;
    }

    public function replaceLine(InvoiceLine $line): void
    {
        $this->assertDraft();
        $this->assertLineCurrency($line);
        if (!isset($this->lines[$line->getPublicId()])) {
            throw new \DomainException('Factuurregel bestaat niet.');
        }
        $this->assertPositionAvailable($line, $line->getPublicId());
        $this->lines[$line->getPublicId()] = $line;
    }

    public function removeLine(string $publicId): void
    {
        $this->assertDraft();
        if (!isset($this->lines[$publicId])) {
            throw new \DomainException('Factuurregel bestaat niet.');
        }
        unset($this->lines[$publicId]);
    }

    public function updateCustomerSnapshot(PartySnapshot $party, AddressSnapshot $address): void
    {
        $this->assertDraft();
        $this->customerSnapshot = $party;
        $this->billingAddressSnapshot = $address;
    }

    public function updateDraftDetails(
        CalendarDate $issueDate,
        CalendarDate $dueDate,
        string $customerReference,
        string $notes
    ): void {
        $this->assertDraft();
        $customerReference = trim($customerReference);
        if ($dueDate->isBefore($issueDate)) {
            throw new \InvalidArgumentException('Vervaldatum mag niet voor de factuurdatum liggen.');
        }
        if (strlen($customerReference) > 128) {
            throw new \InvalidArgumentException('Klantreferentie mag maximaal 128 tekens bevatten.');
        }

        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->customerReference = $customerReference;
        $this->notes = trim($notes);
    }

    public function correctIssueDate(CalendarDate $issueDate): void
    {
        if (!in_array($this->status->getValue(), [InvoiceStatus::DRAFT, InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)) {
            throw new \DomainException('De factuurdatum van deze factuur kan niet worden gewijzigd.');
        }
        if ($this->dueDate->isBefore($issueDate)) {
            throw new \InvalidArgumentException('Vervaldatum mag niet voor de factuurdatum liggen.');
        }

        $this->issueDate = $issueDate;
    }

    public function updateNotes(string $notes): void
    {
        $this->assertDraft();
        $this->notes = trim($notes);
    }

    public function finalize(string $invoiceNumber, int $sequenceValue, int $finalizedAt, InvoiceTotals $totals): void
    {
        $this->assertDraft();
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '' || strlen($invoiceNumber) > 64) {
            throw new \InvalidArgumentException('Definitieve factuur vereist een geldig factuurnummer.');
        }
        if ($sequenceValue < 1) {
            throw new \InvalidArgumentException('Definitieve factuur vereist een positieve nummerreekswaarde.');
        }
        if ($finalizedAt < 0) {
            throw new \InvalidArgumentException('Finalisatietijdstip is ongeldig.');
        }
        if ($this->lines === []) {
            throw new \DomainException('Een factuur zonder regels kan niet worden gefinaliseerd.');
        }

        $net = Money::zero($this->currency);
        $tax = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $net = $net->add($line->calculateNet());
            $tax = $tax->add($line->calculateTax());
        }
        if (!$net->equals($totals->getNet()) || !$tax->equals($totals->getTax())) {
            throw new \DomainException('Finalisatietotalen sluiten niet aan op de factuurregels.');
        }

        $this->invoiceNumber = $invoiceNumber;
        $this->invoiceSequenceValue = $sequenceValue;
        $this->finalizedAt = $finalizedAt;
        $this->finalizedTotals = $totals;
        $this->status = new InvoiceStatus(InvoiceStatus::FINALIZED);
    }

    public function cancelDraft(): void
    {
        $this->assertDraft();
        $this->status = new InvoiceStatus(InvoiceStatus::CANCELLED);
    }

    public function markCredited(): void
    {
        if ($this->status->getValue() !== InvoiceStatus::FINALIZED) {
            throw new \DomainException('Alleen een definitieve factuur kan als gecrediteerd worden gemarkeerd.');
        }
        $this->status = new InvoiceStatus(InvoiceStatus::CREDITED);
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getTenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getCustomerSnapshot(): PartySnapshot
    {
        return $this->customerSnapshot;
    }

    public function getBillingAddressSnapshot(): AddressSnapshot
    {
        return $this->billingAddressSnapshot;
    }

    public function getIssueDate(): CalendarDate
    {
        return $this->issueDate;
    }

    public function getDueDate(): CalendarDate
    {
        return $this->dueDate;
    }

    /** @return InvoiceLine[] */
    public function getLines(): array
    {
        $lines = array_values($this->lines);
        usort($lines, function (InvoiceLine $left, InvoiceLine $right): int {
            return $left->getPosition() <=> $right->getPosition();
        });

        return $lines;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getFinalizedAt(): ?int
    {
        return $this->finalizedAt;
    }

    public function getInvoiceSequenceValue(): ?int
    {
        return $this->invoiceSequenceValue;
    }

    public function getFinalizedTotals(): ?InvoiceTotals
    {
        return $this->finalizedTotals;
    }

    public function getCustomerReference(): string
    {
        return $this->customerReference;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    private function assertDraft(): void
    {
        if ($this->status->getValue() !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Alleen een conceptfactuur mag worden gewijzigd.');
        }
    }

    private function assertLineCurrency(InvoiceLine $line): void
    {
        if (!$line->getUnitPrice()->getCurrency()->equals($this->currency)) {
            throw new \InvalidArgumentException('Factuurregel gebruikt een andere valuta dan de factuur.');
        }
    }

    private function assertPositionAvailable(InvoiceLine $candidate, string $ignorePublicId = ''): void
    {
        foreach ($this->lines as $line) {
            if ($line->getPublicId() !== $ignorePublicId
                && $line->getPosition() === $candidate->getPosition()
            ) {
                throw new \DomainException('Factuurregelpositie moet binnen een factuur uniek zijn.');
            }
        }
    }
}
