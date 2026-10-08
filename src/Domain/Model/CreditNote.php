<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\Model;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;

final class CreditNote
{
    /** @var string */ private $publicId;
    /** @var TenantId */ private $tenantId;
    /** @var string */ private $number;
    /** @var int */ private $sequenceValue;
    /** @var string */ private $originalInvoicePublicId;
    /** @var string */ private $originalInvoiceNumber;
    /** @var Currency */ private $currency;
    /** @var PartySnapshot */ private $customerSnapshot;
    /** @var AddressSnapshot */ private $billingAddressSnapshot;
    /** @var CalendarDate */ private $issueDate;
    /** @var string */ private $reason;
    /** @var InvoiceLine[] */ private $lines;
    /** @var InvoiceTotals */ private $totals;
    /** @var int */ private $finalizedAt;

    /** @param InvoiceLine[] $lines */
    public function __construct(
        string $publicId,
        TenantId $tenantId,
        string $number,
        int $sequenceValue,
        string $originalInvoicePublicId,
        string $originalInvoiceNumber,
        Currency $currency,
        PartySnapshot $customerSnapshot,
        AddressSnapshot $billingAddressSnapshot,
        CalendarDate $issueDate,
        string $reason,
        array $lines,
        InvoiceTotals $totals,
        int $finalizedAt
    ) {
        $publicId = trim($publicId);
        $number = trim($number);
        $originalInvoicePublicId = trim($originalInvoicePublicId);
        $originalInvoiceNumber = trim($originalInvoiceNumber);
        $reason = trim($reason);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Creditnota vereist een geldige publieke id.');
        }
        if ($number === '' || strlen($number) > 64 || $sequenceValue < 1) {
            throw new \InvalidArgumentException('Creditnota vereist een geldig definitief nummer.');
        }
        if ($originalInvoicePublicId === '' || $originalInvoiceNumber === '') {
            throw new \InvalidArgumentException('Creditnota vereist een bronfactuur.');
        }
        if ($reason === '' || strlen($reason) > 1000) {
            throw new \InvalidArgumentException('Reden voor de creditnota is verplicht en maximaal 1000 tekens.');
        }
        if ($lines === [] || $finalizedAt < 0) {
            throw new \InvalidArgumentException('Creditnota vereist regels en een geldig finalisatietijdstip.');
        }

        $net = Money::zero($currency);
        $tax = Money::zero($currency);
        foreach ($lines as $line) {
            if (!$line instanceof InvoiceLine) {
                throw new \InvalidArgumentException('Creditnota bevat een ongeldige regel.');
            }
            if (!$line->getUnitPrice()->getCurrency()->equals($currency)) {
                throw new \InvalidArgumentException('Creditnotaregel gebruikt een andere valuta.');
            }
            if ($line->calculateNet()->getMinorUnits() > 0 || $line->calculateGross()->getMinorUnits() > 0) {
                throw new \DomainException('Creditnotaregels mogen geen positief bedrag bevatten.');
            }
            $net = $net->add($line->calculateNet());
            $tax = $tax->add($line->calculateTax());
        }
        if (!$net->equals($totals->getNet()) || !$tax->equals($totals->getTax())) {
            throw new \DomainException('Creditnotatotalen sluiten niet aan op de regels.');
        }
        if ($totals->getGross()->getMinorUnits() >= 0) {
            throw new \DomainException('Een creditnota moet een negatief eindtotaal hebben.');
        }

        $this->publicId = $publicId;
        $this->tenantId = $tenantId;
        $this->number = $number;
        $this->sequenceValue = $sequenceValue;
        $this->originalInvoicePublicId = $originalInvoicePublicId;
        $this->originalInvoiceNumber = $originalInvoiceNumber;
        $this->currency = $currency;
        $this->customerSnapshot = $customerSnapshot;
        $this->billingAddressSnapshot = $billingAddressSnapshot;
        $this->issueDate = $issueDate;
        $this->reason = $reason;
        $this->lines = array_values($lines);
        $this->totals = $totals;
        $this->finalizedAt = $finalizedAt;
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getNumber(): string { return $this->number; }
    public function getSequenceValue(): int { return $this->sequenceValue; }
    public function getOriginalInvoicePublicId(): string { return $this->originalInvoicePublicId; }
    public function getOriginalInvoiceNumber(): string { return $this->originalInvoiceNumber; }
    public function getCurrency(): Currency { return $this->currency; }
    public function getCustomerSnapshot(): PartySnapshot { return $this->customerSnapshot; }
    public function getBillingAddressSnapshot(): AddressSnapshot { return $this->billingAddressSnapshot; }
    public function getIssueDate(): CalendarDate { return $this->issueDate; }
    public function getReason(): string { return $this->reason; }
    /** @return InvoiceLine[] */ public function getLines(): array { return $this->lines; }
    public function getTotals(): InvoiceTotals { return $this->totals; }
    public function getFinalizedAt(): int { return $this->finalizedAt; }
}
