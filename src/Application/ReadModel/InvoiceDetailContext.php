<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\ReadModel;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

/**
 * Beperkte, immutable context voor optionele uitbreidingen op het factuurdetail.
 */
final class InvoiceDetailContext
{
    /** @var string */ private $publicId;
    /** @var string */ private $number;
    /** @var string */ private $status;
    /** @var string */ private $paymentStatus;
    /** @var Money */ private $grossTotal;

    public function __construct(
        string $publicId,
        string $number,
        string $status,
        string $paymentStatus,
        Money $grossTotal
    ) {
        $publicId = trim($publicId);
        $number = trim($number);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Factuurdetail vereist een geldige publieke id.');
        }
        if ($number === '') {
            throw new \InvalidArgumentException('Factuurdetail vereist een definitief factuurnummer.');
        }
        if (!in_array($status, InvoiceStatus::values(), true)) {
            throw new \InvalidArgumentException('Factuurdetail bevat een ongeldige documentstatus.');
        }
        if (!in_array($paymentStatus, PaymentStatus::values(), true)) {
            throw new \InvalidArgumentException('Factuurdetail bevat een ongeldige betaalstatus.');
        }

        $this->publicId = $publicId;
        $this->number = $number;
        $this->status = $status;
        $this->paymentStatus = $paymentStatus;
        $this->grossTotal = $grossTotal;
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getNumber(): string { return $this->number; }
    public function getStatus(): string { return $this->status; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getGrossTotal(): Money { return $this->grossTotal; }
    public function canRegisterPayments(): bool { return $this->status === InvoiceStatus::FINALIZED; }
}
