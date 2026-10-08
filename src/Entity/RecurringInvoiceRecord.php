<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_recurring,repository=Flexgrid\Modules\AdminInvoice\Repository\RecurringInvoiceRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_due[columns={tenantId,active,nextIssueDate}]
 */
final class RecurringInvoiceRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $publicId;
    /** @FG\Column[type=varchar,length=255,required=true] */ protected $name;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $customerPublicId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $catalogItemPublicId;
    /** @FG\Column[type=varchar,length=10,required=true] */ protected $nextIssueDate;
    /** @FG\Column[type=int,required=true] */ protected $dayOfMonth;
    /** @FG\Column[type=int,required=true] */ protected $frequencyMonths;
    /** @FG\Column[type=int,required=true] */ protected $dueDays;
    /** @FG\Column[type=tinyint,required=true] */ protected $active;
    /** @FG\Column[type=text] */ protected $lastError;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;
    /** @FG\Column[type=bigint,required=true] */ protected $updatedAt;


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
     * Get the value of customerPublicId
     */
    public function getCustomerPublicId()
    {
        return $this->customerPublicId;
    }

    /**
     * Set the value of customerPublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setCustomerPublicId($value)
    {
        $this->customerPublicId = $value;
        return $this;
    }

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

    /**
     * Get the value of nextIssueDate
     */
    public function getNextIssueDate()
    {
        return $this->nextIssueDate;
    }

    /**
     * Set the value of nextIssueDate
     *
     * @param mixed $value
     * @return $this
     */
    public function setNextIssueDate($value)
    {
        $this->nextIssueDate = $value;
        return $this;
    }

    /**
     * Get the value of dayOfMonth
     */
    public function getDayOfMonth()
    {
        return $this->dayOfMonth;
    }

    /**
     * Set the value of dayOfMonth
     *
     * @param mixed $value
     * @return $this
     */
    public function setDayOfMonth($value)
    {
        $this->dayOfMonth = $value;
        return $this;
    }

    /**
     * Get the value of frequencyMonths
     */
    public function getFrequencyMonths()
    {
        return $this->frequencyMonths;
    }

    /**
     * Set the value of frequencyMonths
     *
     * @param mixed $value
     * @return $this
     */
    public function setFrequencyMonths($value)
    {
        $this->frequencyMonths = $value;
        return $this;
    }

    /**
     * Get the value of dueDays
     */
    public function getDueDays()
    {
        return $this->dueDays;
    }

    /**
     * Set the value of dueDays
     *
     * @param mixed $value
     * @return $this
     */
    public function setDueDays($value)
    {
        $this->dueDays = $value;
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
     * Get the value of lastError
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Set the value of lastError
     *
     * @param mixed $value
     * @return $this
     */
    public function setLastError($value)
    {
        $this->lastError = $value;
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
