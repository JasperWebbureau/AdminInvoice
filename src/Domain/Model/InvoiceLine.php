<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\Model;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

final class InvoiceLine
{
    /** @var string */
    private $publicId;
    /** @var string */
    private $description;
    /** @var Quantity */
    private $quantity;
    /** @var string */
    private $unit;
    /** @var Money */
    private $unitPrice;
    /** @var TaxRate */
    private $taxRate;
    /** @var TaxCategory */
    private $taxCategory;
    /** @var int */
    private $position;
    /** @var string */ private $catalogItemPublicId;

    public function __construct(
        string $publicId,
        string $description,
        Quantity $quantity,
        string $unit,
        Money $unitPrice,
        TaxRate $taxRate,
        TaxCategory $taxCategory,
        int $position,
        string $catalogItemPublicId = ''
    ) {
        $publicId = trim($publicId);
        $description = trim($description);
        $unit = trim($unit);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Factuurregel vereist een geldige publieke id.');
        }
        if ($description === '') {
            throw new \InvalidArgumentException('Factuurregelomschrijving is verplicht.');
        }
        if ($quantity->isZero()) {
            throw new \InvalidArgumentException('Factuurregelhoeveelheid mag niet nul zijn.');
        }
        if ($unit === '' || strlen($unit) > 32) {
            throw new \InvalidArgumentException('Factuurregeleenheid is verplicht en maximaal 32 tekens.');
        }
        if ($position < 0) {
            throw new \InvalidArgumentException('Factuurregelpositie mag niet negatief zijn.');
        }
        if (strlen(trim($catalogItemPublicId)) > 64) {
            throw new \InvalidArgumentException('Catalogusreferentie is te lang.');
        }
        if ($taxCategory->requiresZeroRate() && $taxRate->getBasisPoints() !== 0) {
            throw new \InvalidArgumentException('Deze btw-categorie vereist een nultarief.');
        }

        $this->publicId = $publicId;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unit = $unit;
        $this->unitPrice = $unitPrice;
        $this->taxRate = $taxRate;
        $this->taxCategory = $taxCategory;
        $this->position = $position;
        $this->catalogItemPublicId = trim($catalogItemPublicId);
    }

    public function calculateNet(): Money
    {
        return $this->unitPrice->multiplyScaled($this->quantity->getScaledValue(), Quantity::SCALE);
    }

    public function calculateTax(): Money
    {
        $net = $this->calculateNet();
        if ($this->taxCategory->requiresZeroRate()) {
            return Money::zero($net->getCurrency());
        }

        return $this->taxRate->calculateTax($net);
    }

    public function calculateGross(): Money
    {
        return $this->calculateNet()->add($this->calculateTax());
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getCatalogItemPublicId(): string
    {
        return $this->catalogItemPublicId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): Quantity
    {
        return $this->quantity;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getTaxRate(): TaxRate
    {
        return $this->taxRate;
    }

    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategory;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
