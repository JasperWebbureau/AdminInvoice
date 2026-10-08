<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_dispatch,repository=Flexgrid\Modules\AdminInvoice\Repository\InvoiceDispatchRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_invoice_kind[columns={tenantId,invoicePublicId,kind},unique=true]
 */
final class InvoiceDispatchRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $invoicePublicId;
    /** @FG\Column[type=varchar,length=24,required=true] */ protected $kind;
    /** @FG\Column[type=varchar,length=255,required=true] */ protected $recipient;
    /** @FG\Column[type=varchar,length=20,required=true] */ protected $status;
    /** @FG\Column[type=varchar,length=255,required=true] */ protected $subject;
    /** @FG\Column[type=text] */ protected $error;
    /** @FG\Column[type=bigint] */ protected $sentAt;
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
     * Get the value of kind
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * Set the value of kind
     *
     * @param mixed $value
     * @return $this
     */
    public function setKind($value)
    {
        $this->kind = $value;
        return $this;
    }

    /**
     * Get the value of recipient
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * Set the value of recipient
     *
     * @param mixed $value
     * @return $this
     */
    public function setRecipient($value)
    {
        $this->recipient = $value;
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
     * Get the value of subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set the value of subject
     *
     * @param mixed $value
     * @return $this
     */
    public function setSubject($value)
    {
        $this->subject = $value;
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
     * Get the value of sentAt
     */
    public function getSentAt()
    {
        return $this->sentAt;
    }

    /**
     * Set the value of sentAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setSentAt($value)
    {
        $this->sentAt = $value;
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
