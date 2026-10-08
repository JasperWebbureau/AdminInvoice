<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\Command;

final class AddInvoiceLineCommand
{
    /** @var string */
    private $invoicePublicId;
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
    /** @var string */ private $catalogItemPublicId;

    public function __construct(
        string $invoicePublicId,
        string $description,
        string $quantity,
        string $unit,
        string $unitPrice,
        string $taxRate,
        string $taxCategory,
        string $catalogItemPublicId = ''
    ) {
        $this->invoicePublicId = trim($invoicePublicId);
        $this->description = trim($description);
        $this->quantity = trim($quantity);
        $this->unit = trim($unit);
        $this->unitPrice = trim($unitPrice);
        $this->taxRate = trim($taxRate);
        $this->taxCategory = trim($taxCategory);
        $this->catalogItemPublicId = trim($catalogItemPublicId);
    }

    public function getInvoicePublicId(): string { return $this->invoicePublicId; }
    public function getDescription(): string { return $this->description; }
    public function getQuantity(): string { return $this->quantity; }
    public function getUnit(): string { return $this->unit; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function getTaxRate(): string { return $this->taxRate; }
    public function getTaxCategory(): string { return $this->taxCategory; }
    public function getCatalogItemPublicId(): string { return $this->catalogItemPublicId; }
}
