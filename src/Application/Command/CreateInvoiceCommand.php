<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\Command;

final class CreateInvoiceCommand
{
    /** @var string */
    private $currency;
    /** @var string */
    private $issueDate;
    /** @var string */
    private $dueDate;
    /** @var array */
    private $customerSnapshot;
    /** @var array */
    private $billingAddressSnapshot;
    /** @var array */
    private $lines;
    /** @var string */
    private $customerReference;
    /** @var string */
    private $notes;
    /** @var string */
    private $source;
    /** @var string */
    private $externalId;

    public function __construct(
        string $currency,
        string $issueDate,
        string $dueDate,
        array $customerSnapshot,
        array $billingAddressSnapshot,
        array $lines = [],
        string $customerReference = '',
        string $notes = '',
        string $source = '',
        string $externalId = ''
    ) {
        $this->currency = trim($currency);
        $this->issueDate = trim($issueDate);
        $this->dueDate = trim($dueDate);
        $this->customerSnapshot = $customerSnapshot;
        $this->billingAddressSnapshot = $billingAddressSnapshot;
        $this->lines = $lines;
        $this->customerReference = trim($customerReference);
        $this->notes = trim($notes);
        $this->source = trim($source);
        $this->externalId = trim($externalId);
    }

    public function getCurrency(): string { return $this->currency; }
    public function getIssueDate(): string { return $this->issueDate; }
    public function getDueDate(): string { return $this->dueDate; }
    public function getCustomerSnapshot(): array { return $this->customerSnapshot; }
    public function getBillingAddressSnapshot(): array { return $this->billingAddressSnapshot; }
    public function getLines(): array { return $this->lines; }
    public function getCustomerReference(): string { return $this->customerReference; }
    public function getNotes(): string { return $this->notes; }
    public function getSource(): string { return $this->source; }
    public function getExternalId(): string { return $this->externalId; }
}
