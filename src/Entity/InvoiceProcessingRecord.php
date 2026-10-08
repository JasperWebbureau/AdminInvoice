<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_processing,repository=Flexgrid\Modules\AdminInvoice\Repository\InvoiceProcessingRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_invoice[columns={tenantId,invoicePublicId},unique=true]
 */
final class InvoiceProcessingRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $invoicePublicId;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $status;
    /** @FG\Column[type=tinyint,required=true] */ protected $automaticApproval;
    /** @FG\Column[type=text] */ protected $error;
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
     * Get the value of invoicePublicId
     */
    public function getInvoicePublicId()
    {
        return $this->invoicePublicId;
    }

    /**
     * Set the value of invoicePublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoicePublicId($value)
    {
        $this->invoicePublicId = $value;
        return $this;
    }

    /**
     * Get the value of status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @param mixed $value
     * @return $this
     */
    public function setStatus($value)
    {
        $this->status = $value;
        return $this;
    }

    /**
     * Get the value of automaticApproval
     */
    public function getAutomaticApproval()
    {
        return $this->automaticApproval;
    }

    /**
     * Set the value of automaticApproval
     *
     * @param mixed $value
     * @return $this
     */
    public function setAutomaticApproval($value)
    {
        $this->automaticApproval = $value;
        return $this;
    }

    /**
     * Get the value of error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set the value of error
     *
     * @param mixed $value
     * @return $this
     */
    public function setError($value)
    {
        $this->error = $value;
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
