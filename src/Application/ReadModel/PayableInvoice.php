<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\ReadModel;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;

final class PayableInvoice
{
    /** @var TenantId */ private $tenantId;
    /** @var string */ private $publicId;
    /** @var string */ private $number;
    /** @var string */ private $status;
    /** @var Money */ private $grossTotal;

    public function __construct(
        TenantId $tenantId,
        string $publicId,
        string $number,
        string $status,
        Money $grossTotal
    ) {
        $this->tenantId = $tenantId;
        $this->publicId = $publicId;
        $this->number = $number;
        $this->status = $status;
        $this->grossTotal = $grossTotal;
    }

    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getPublicId(): string { return $this->publicId; }
    public function getNumber(): string { return $this->number; }
    public function getStatus(): string { return $this->status; }
    public function getGrossTotal(): Money { return $this->grossTotal; }
    public function isPayable(): bool { return $this->status === InvoiceStatus::FINALIZED; }
}
