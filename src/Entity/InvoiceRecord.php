<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * Autowire-opslagrecord. Domeinregels leven in Domain\Model\Invoice.
 *
 * @FG\Entity[name=admin_invoice,repository=Flexgrid\Modules\AdminInvoice\Repository\InvoiceRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_invoice_number[columns={tenantId,invoiceNumber},unique=true]
 * @FG\Index::tenant_source_external[columns={tenantId,source,externalId},unique=true]
 * @FG\Index::tenant_status_issue[columns={tenantId,status,issueDate}]
 */
final class InvoiceRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */
    protected $id;

    /** @FG\Column[type=varchar,length=64,required=true] */
    protected $tenantId;

    /** @FG\Column[type=varchar,length=64,required=true] */
    protected $publicId;

    /** @FG\Column[type=varchar,length=64] */
    protected $invoiceNumber;

    /** @FG\Column[type=bigint] */
    protected $invoiceSequenceValue;

    /** @FG\Column[type=varchar,length=20,required=true] */
    protected $status;

    /** @FG\Column[type=varchar,length=24,required=true] */
    protected $paymentStatus;

    /** @FG\Column[type=varchar,length=3,required=true] */
    protected $currency;

    /** @FG\Column[type=varchar,length=128] */
    protected $customerReference;

    /** @FG\Column[type=text,required=true] */
    protected $customerSnapshot;

    /** @FG\Column[type=text,required=true] */
    protected $billingAddressSnapshot;

    /** @FG\Column[type=varchar,length=10,required=true] */
    protected $issueDate;

    /** @FG\Column[type=varchar,length=10,required=true] */
    protected $dueDate;

    /** @FG\Column[type=bigint] */
    protected $finalizedAt;

    /** @FG\Column[type=text] */
    protected $notes;

    /** @FG\Column[type=varchar,length=64] */
    protected $source;

    /** @FG\Column[type=varchar,length=128] */
    protected $externalId;

    /** @FG\Column[type=bigint,required=true] */
    protected $netTotalMinor;

    /** @FG\Column[type=bigint,required=true] */
    protected $taxTotalMinor;

    /** @FG\Column[type=bigint,required=true] */
    protected $grossTotalMinor;

    /** @FG\Column[type=text,required=true] */
    protected $taxSummarySnapshot;

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
     * Get the value of invoiceNumber
     */
    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }

    /**
     * Set the value of invoiceNumber
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoiceNumber($value)
    {
        $this->invoiceNumber = $value;
        return $this;
    }

    /**
     * Get the value of invoiceSequenceValue
     */
    public function getInvoiceSequenceValue()
    {
        return $this->invoiceSequenceValue;
    }

    /**
     * Set the value of invoiceSequenceValue
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoiceSequenceValue($value)
    {
        $this->invoiceSequenceValue = $value;
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
     * Get the value of paymentStatus
     */
    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    /**
     * Set the value of paymentStatus
     *
     * @param mixed $value
     * @return $this
     */
    public function setPaymentStatus($value)
    {
        $this->paymentStatus = $value;
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
     * Get the value of customerReference
     */
    public function getCustomerReference()
    {
        return $this->customerReference;
    }

    /**
     * Set the value of customerReference
     *
     * @param mixed $value
     * @return $this
     */
    public function setCustomerReference($value)
    {
        $this->customerReference = $value;
        return $this;
    }

    /**
     * Get the value of customerSnapshot
     */
    public function getCustomerSnapshot()
    {
        return $this->customerSnapshot;
    }

    /**
     * Set the value of customerSnapshot
     *
     * @param mixed $value
     * @return $this
     */
    public function setCustomerSnapshot($value)
    {
        $this->customerSnapshot = $value;
        return $this;
    }

    /**
     * Get the value of billingAddressSnapshot
     */
    public function getBillingAddressSnapshot()
    {
        return $this->billingAddressSnapshot;
    }

    /**
     * Set the value of billingAddressSnapshot
     *
     * @param mixed $value
     * @return $this
     */
    public function setBillingAddressSnapshot($value)
    {
        $this->billingAddressSnapshot = $value;
        return $this;
    }

    /**
     * Get the value of issueDate
     */
    public function getIssueDate()
    {
        return $this->issueDate;
    }

    /**
     * Set the value of issueDate
     *
     * @param mixed $value
     * @return $this
     */
    public function setIssueDate($value)
    {
        $this->issueDate = $value;
        return $this;
    }

    /**
     * Get the value of dueDate
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * Set the value of dueDate
     *
     * @param mixed $value
     * @return $this
     */
    public function setDueDate($value)
    {
        $this->dueDate = $value;
        return $this;
    }

    /**
     * Get the value of finalizedAt
     */
    public function getFinalizedAt()
    {
        return $this->finalizedAt;
    }

    /**
     * Set the value of finalizedAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setFinalizedAt($value)
    {
        $this->finalizedAt = $value;
        return $this;
    }

    /**
     * Get the value of notes
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Set the value of notes
     *
     * @param mixed $value
     * @return $this
     */
    public function setNotes($value)
    {
        $this->notes = $value;
        return $this;
    }

    /**
     * Get the value of source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set the value of source
     *
     * @param mixed $value
     * @return $this
     */
    public function setSource($value)
    {
        $this->source = $value;
        return $this;
    }

    /**
     * Get the value of externalId
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * Set the value of externalId
     *
     * @param mixed $value
     * @return $this
     */
    public function setExternalId($value)
    {
        $this->externalId = $value;
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
     * Get the value of taxSummarySnapshot
     */
    public function getTaxSummarySnapshot()
    {
        return $this->taxSummarySnapshot;
    }

    /**
     * Set the value of taxSummarySnapshot
     *
     * @param mixed $value
     * @return $this
     */
    public function setTaxSummarySnapshot($value)
    {
        $this->taxSummarySnapshot = $value;
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
