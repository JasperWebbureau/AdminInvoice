<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\ReadModel;

final class InvoiceListItem
{
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDIT_NOTE = 'credit_note';

    /** @var string */ private $publicId;
    /** @var string */ private $invoiceNumber;
    /** @var string */ private $customerName;
    /** @var string */ private $customerReference;
    /** @var string */ private $issueDate;
    /** @var string */ private $dueDate;
    /** @var int */ private $grossTotalMinor;
    /** @var string */ private $currency;
    /** @var string */ private $status;
    /** @var string */ private $paymentStatus;
    /** @var string */ private $documentType;
    /** @var string */ private $originalInvoiceNumber;

    public function __construct(
        string $publicId,
        string $invoiceNumber,
        string $customerName,
        string $customerReference,
        string $issueDate,
        string $dueDate,
        int $grossTotalMinor,
        string $currency,
        string $status,
        string $paymentStatus,
        string $documentType = self::TYPE_INVOICE,
        string $originalInvoiceNumber = ''
    ) {
        if (!in_array($documentType, [self::TYPE_INVOICE, self::TYPE_CREDIT_NOTE], true)) {
            throw new \InvalidArgumentException('Ongeldig documenttype in factuuroverzicht.');
        }
        $this->publicId = $publicId;
        $this->invoiceNumber = $invoiceNumber;
        $this->customerName = $customerName;
        $this->customerReference = $customerReference;
        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->grossTotalMinor = $grossTotalMinor;
        $this->currency = $currency;
        $this->status = $status;
        $this->paymentStatus = $paymentStatus;
        $this->documentType = $documentType;
        $this->originalInvoiceNumber = $originalInvoiceNumber;
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function getCustomerName(): string { return $this->customerName; }
    public function getCustomerReference(): string { return $this->customerReference; }
    public function getIssueDate(): string { return $this->issueDate; }
    public function getDueDate(): string { return $this->dueDate; }
    public function getGrossTotalMinor(): int { return $this->grossTotalMinor; }
    public function getCurrency(): string { return $this->currency; }
    public function getStatus(): string { return $this->status; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getDocumentType(): string { return $this->documentType; }
    public function getOriginalInvoiceNumber(): string { return $this->originalInvoiceNumber; }
    public function isCreditNote(): bool { return $this->documentType === self::TYPE_CREDIT_NOTE; }
}
