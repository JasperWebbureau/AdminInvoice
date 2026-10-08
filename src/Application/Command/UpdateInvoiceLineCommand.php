<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\Command;

final class UpdateInvoiceLineCommand
{
    /** @var string */
    private $invoicePublicId;
    /** @var string */
    private $linePublicId;
    /** @var string */
    private $description;
    /** @var string */
    private $quantity;
    /** @var string */
    private $unit;
    /** @var string */
    private $unitPrice;
    /** @var string */
    private $taxRate;
    /** @var string */
    private $taxCategory;

    public function __construct(
        string $invoicePublicId,
        string $linePublicId,
        string $description,
        string $quantity,
        string $unit,
        string $unitPrice,
        string $taxRate,
        string $taxCategory
    ) {
        $this->invoicePublicId = trim($invoicePublicId);
        $this->linePublicId = trim($linePublicId);
        $this->description = trim($description);
        $this->quantity = trim($quantity);
        $this->unit = trim($unit);
        $this->unitPrice = trim($unitPrice);
        $this->taxRate = trim($taxRate);
        $this->taxCategory = trim($taxCategory);
    }

    public function getInvoicePublicId(): string { return $this->invoicePublicId; }
    public function getLinePublicId(): string { return $this->linePublicId; }
    public function getDescription(): string { return $this->description; }
    public function getQuantity(): string { return $this->quantity; }
    public function getUnit(): string { return $this->unit; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function getTaxRate(): string { return $this->taxRate; }
    public function getTaxCategory(): string { return $this->taxCategory; }
}
