<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * Autowire-opslagrecord. Bedragen zijn minor units en quantity heeft schaal 4.
 *
 * @FG\Entity[name=admin_invoice_line,repository=Flexgrid\Modules\AdminInvoice\Repository\InvoiceLineRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_invoice_position[columns={tenantId,invoiceId,position},unique=true]
 * @FG\Index::tenant_invoice[columns={tenantId,invoiceId}]
 */
final class InvoiceLineRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */
    protected $id;

    /** @FG\Column[type=varchar,length=64,required=true] */
    protected $tenantId;

    /** @FG\Column[type=varchar,length=64,required=true] */
    protected $publicId;

    /** @FG\Column[type=int,required=true] */
    protected $invoiceId;

    /** @FG\Column[type=text,required=true] */
    protected $description;

    /** @FG\Column[type=bigint,required=true] */
    protected $quantityScaled;

    /** @FG\Column[type=varchar,length=32,required=true] */
    protected $unit;

    /** @FG\Column[type=bigint,required=true] */
    protected $unitPriceMinor;

    /** @FG\Column[type=int,required=true] */
    protected $taxRateBasisPoints;

    /** @FG\Column[type=varchar,length=32,required=true] */
    protected $taxCategory;

    /** @FG\Column[type=varchar,length=64] */
    protected $catalogItemPublicId;

    /** @FG\Column[type=int,required=true] */
    protected $position;

    /** @FG\Column[type=bigint,required=true] */
    protected $netTotalMinor;

    /** @FG\Column[type=bigint,required=true] */
    protected $taxTotalMinor;

    /** @FG\Column[type=bigint,required=true] */
    protected $grossTotalMinor;

    /** @FG\Column[type=bigint,required=true] */
    protected $createdAt;

    /** @FG\Column[type=bigint,required=true] */
    protected $updatedAt;


    // --- Auto-generated getters and setters ---

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @param mixed $value
     * @return $this
     */
    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    /**
     * Get the value of tenantId
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     * Set the value of tenantId
     *
     * @param mixed $value
     * @return $this
     */
    public function setTenantId($value)
    {
        $this->tenantId = $value;
        return $this;
    }

    /**
     * Get the value of publicId
     */
    public function getPublicId()
    {
        return $this->publicId;
    }

    /**
     * Set the value of publicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setPublicId($value)
    {
        $this->publicId = $value;
        return $this;
    }

    /**
     * Get the value of invoiceId
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * Set the value of invoiceId
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoiceId($value)
    {
        $this->invoiceId = $value;
        return $this;
    }

    /**
     * Get the value of description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @param mixed $value
     * @return $this
     */
    public function setDescription($value)
    {
        $this->description = $value;
        return $this;
    }

    /**
     * Get the value of quantityScaled
     */
    public function getQuantityScaled()
    {
        return $this->quantityScaled;
    }

    /**
     * Set the value of quantityScaled
     *
     * @param mixed $value
     * @return $this
     */
    public function setQuantityScaled($value)
    {
        $this->quantityScaled = $value;
        return $this;
    }

    /**
     * Get the value of unit
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * Set the value of unit
     *
     * @param mixed $value
     * @return $this
     */
    public function setUnit($value)
    {
        $this->unit = $value;
        return $this;
    }

    /**
     * Get the value of unitPriceMinor
     */
    public function getUnitPriceMinor()
    {
        return $this->unitPriceMinor;
    }

    /**
     * Set the value of unitPriceMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setUnitPriceMinor($value)
    {
        $this->unitPriceMinor = $value;
        return $this;
    }

    /**
     * Get the value of taxRateBasisPoints
     */
    public function getTaxRateBasisPoints()
    {
        return $this->taxRateBasisPoints;
    }

    /**
     * Set the value of taxRateBasisPoints
     *
     * @param mixed $value
     * @return $this
     */
    public function setTaxRateBasisPoints($value)
    {
        $this->taxRateBasisPoints = $value;
        return $this;
    }

    /**
     * Get the value of taxCategory
     */
    public function getTaxCategory()
    {
        return $this->taxCategory;
    }

    /**
     * Set the value of taxCategory
     *
     * @param mixed $value
     * @return $this
     */
    public function setTaxCategory($value)
    {
        $this->taxCategory = $value;
        return $this;
    }

    /**
     * Get the value of position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set the value of position
     *
     * @param mixed $value
     * @return $this
     */
    public function setPosition($value)
    {
        $this->position = $value;
        return $this;
    }

    /**
     * Get the value of netTotalMinor
     */
    public function getNetTotalMinor()
    {
        return $this->netTotalMinor;
    }

    /**
     * Set the value of netTotalMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setNetTotalMinor($value)
    {
        $this->netTotalMinor = $value;
        return $this;
    }

    /**
     * Get the value of taxTotalMinor
     */
    public function getTaxTotalMinor()
    {
        return $this->taxTotalMinor;
    }

    /**
     * Set the value of taxTotalMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setTaxTotalMinor($value)
    {
        $this->taxTotalMinor = $value;
        return $this;
    }

    /**
     * Get the value of grossTotalMinor
     */
    public function getGrossTotalMinor()
    {
        return $this->grossTotalMinor;
    }

    /**
     * Set the value of grossTotalMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setGrossTotalMinor($value)
    {
        $this->grossTotalMinor = $value;
        return $this;
    }

    /**
     * Get the value of createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->createdAt = $value;
        return $this;
    }

    /**
     * Get the value of updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->updatedAt = $value;
        return $this;
    }

    /**
     * Get the value of makeTime
     */
    public function getMakeTime()
    {
        return $this->makeTime;
    }

    /**
     * Set the value of makeTime
     *
     * @param mixed $value
     * @return $this
     */
    public function setMakeTime($value)
    {
        $this->makeTime = $value;
        return $this;
    }



    // --- Auto-generated getters and setters ---

    /**
     * Get the value of catalogItemPublicId
     */
    public function getCatalogItemPublicId()
    {
        return $this->catalogItemPublicId;
    }

    /**
     * Set the value of catalogItemPublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setCatalogItemPublicId($value)
    {
        $this->catalogItemPublicId = $value;
        return $this;
    }

}
