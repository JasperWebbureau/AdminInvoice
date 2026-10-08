<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\Command;

final class UpdateDraftInvoiceCommand
{
    /** @var string */
    private $publicId;
    /** @var string */
    private $issueDate;
    /** @var string */
    private $dueDate;
    /** @var array */
    private $customerSnapshot;
    /** @var array */
    private $billingAddressSnapshot;
    /** @var string */
    private $customerReference;
    /** @var string */
    private $notes;

    public function __construct(
        string $publicId,
        string $issueDate,
        string $dueDate,
        array $customerSnapshot,
        array $billingAddressSnapshot,
        string $customerReference = '',
        string $notes = ''
    ) {
        $this->publicId = trim($publicId);
        $this->issueDate = trim($issueDate);
        $this->dueDate = trim($dueDate);
        $this->customerSnapshot = $customerSnapshot;
        $this->billingAddressSnapshot = $billingAddressSnapshot;
        $this->customerReference = trim($customerReference);
        $this->notes = trim($notes);
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getIssueDate(): string { return $this->issueDate; }
    public function getDueDate(): string { return $this->dueDate; }
    public function getCustomerSnapshot(): array { return $this->customerSnapshot; }
    public function getBillingAddressSnapshot(): array { return $this->billingAddressSnapshot; }
    public function getCustomerReference(): string { return $this->customerReference; }
    public function getNotes(): string { return $this->notes; }
}
