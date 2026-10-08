<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\ReadModel;

final class InvoiceOverviewSummary
{
    /** @var int */ private $invoiceCount;
    /** @var int */ private $revenueNetMinor;
    /** @var int */ private $paidGrossMinor;
    /** @var int */ private $outstandingGrossMinor;
    /** @var string */ private $currency;

    public function __construct(
        int $invoiceCount,
        int $revenueNetMinor,
        int $paidGrossMinor,
        int $outstandingGrossMinor,
        string $currency
    ) {
        $this->invoiceCount = $invoiceCount;
        $this->revenueNetMinor = $revenueNetMinor;
        $this->paidGrossMinor = $paidGrossMinor;
        $this->outstandingGrossMinor = $outstandingGrossMinor;
        $this->currency = $currency;
    }

    public function getInvoiceCount(): int { return $this->invoiceCount; }
    public function getRevenueNetMinor(): int { return $this->revenueNetMinor; }
    public function getPaidGrossMinor(): int { return $this->paidGrossMinor; }
    public function getOutstandingGrossMinor(): int { return $this->outstandingGrossMinor; }
    public function getCurrency(): string { return $this->currency; }
}
