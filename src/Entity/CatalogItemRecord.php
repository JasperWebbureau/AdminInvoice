<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_catalog_item,repository=Flexgrid\Modules\AdminInvoice\Repository\CatalogItemRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_active_name[columns={tenantId,active,name}]
 * @FG\Index::tenant_parent[columns={tenantId,parentPublicId}]
 */
final class CatalogItemRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $publicId;
    /** @FG\Column[type=varchar,length=255,required=true] */ protected $name;
    /** @FG\Column[type=varchar,length=16] */ protected $itemType;
    /** @FG\Column[type=varchar,length=64] */ protected $parentPublicId;
    /** @FG\Column[type=varchar,length=64] */ protected $variantKey;
    /** @FG\Column[type=varchar,length=128] */ protected $chooseFunction;
    /** @FG\Column[type=varchar,length=500,required=true] */ protected $description;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $quantity;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $unit;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $unitPrice;
    /** @FG\Column[type=varchar,length=3,required=true] */ protected $currency;
    /** @FG\Column[type=varchar,length=8,required=true] */ protected $taxRate;
    /** @FG\Column[type=varchar,length=24,required=true] */ protected $taxCategory;
    /** @FG\Column[type=tinyint,required=true] */ protected $active;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;
    /** @FG\Column[type=bigint,required=true] */ protected $updatedAt;

    public function getItemType() { return $this->itemType; }
    public function setItemType($value) { $this->itemType = $value; return $this; }
    public function getParentPublicId() { return $this->parentPublicId; }
    public function setParentPublicId($value) { $this->parentPublicId = $value; return $this; }
    public function getVariantKey() { return $this->variantKey; }
    public function setVariantKey($value) { $this->variantKey = $value; return $this; }
    public function getChooseFunction() { return $this->chooseFunction; }
    public function setChooseFunction($value) { $this->chooseFunction = $value; return $this; }


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
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @param mixed $value
     * @return $this
     */
    public function setName($value)
    {
        $this->name = $value;
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
     * Get the value of quantity
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set the value of quantity
     *
     * @param mixed $value
     * @return $this
     */
    public function setQuantity($value)
    {
        $this->quantity = $value;
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
     * Get the value of unitPrice
     */
    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    /**
     * Set the value of unitPrice
     *
     * @param mixed $value
     * @return $this
     */
    public function setUnitPrice($value)
    {
        $this->unitPrice = $value;
        return $this;
    }

    /**
     * Get the value of currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the value of currency
     *
     * @param mixed $value
     * @return $this
     */
    public function setCurrency($value)
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Get the value of taxRate
     */
    public function getTaxRate()
    {
        return $this->taxRate;
    }

    /**
     * Set the value of taxRate
     *
     * @param mixed $value
     * @return $this
     */
    public function setTaxRate($value)
    {
        $this->taxRate = $value;
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
     * Get the value of active
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set the value of active
     *
     * @param mixed $value
     * @return $this
     */
    public function setActive($value)
    {
        $this->active = $value;
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

}
