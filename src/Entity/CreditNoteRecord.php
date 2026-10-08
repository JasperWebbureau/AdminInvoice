<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_credit_note,repository=Flexgrid\Modules\AdminInvoice\Repository\CreditNoteRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_public[columns={tenantId,publicId},unique=true]
 * @FG\Index::tenant_credit_number[columns={tenantId,creditNoteNumber},unique=true]
 * @FG\Index::tenant_original_invoice[columns={tenantId,originalInvoicePublicId}]
 * @FG\Index::tenant_issue_date[columns={tenantId,issueDate}]
 */
final class CreditNoteRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $publicId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $creditNoteNumber;
    /** @FG\Column[type=bigint,required=true] */ protected $sequenceValue;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $originalInvoicePublicId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $originalInvoiceNumber;
    /** @FG\Column[type=varchar,length=3,required=true] */ protected $currency;
    /** @FG\Column[type=text,required=true] */ protected $customerSnapshot;
    /** @FG\Column[type=text,required=true] */ protected $billingAddressSnapshot;
    /** @FG\Column[type=varchar,length=10,required=true] */ protected $issueDate;
    /** @FG\Column[type=text,required=true] */ protected $reason;
    /** @FG\Column[type=bigint,required=true] */ protected $finalizedAt;
    /** @FG\Column[type=bigint,required=true] */ protected $netTotalMinor;
    /** @FG\Column[type=bigint,required=true] */ protected $taxTotalMinor;
    /** @FG\Column[type=bigint,required=true] */ protected $grossTotalMinor;
    /** @FG\Column[type=text,required=true] */ protected $taxSummarySnapshot;
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
     * Get the value of creditNoteNumber
     */
    public function getCreditNoteNumber()
    {
        return $this->creditNoteNumber;
    }

    /**
     * Set the value of creditNoteNumber
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreditNoteNumber($value)
    {
        $this->creditNoteNumber = $value;
        return $this;
    }

    /**
     * Get the value of sequenceValue
     */
    public function getSequenceValue()
    {
        return $this->sequenceValue;
    }

    /**
     * Set the value of sequenceValue
     *
     * @param mixed $value
     * @return $this
     */
    public function setSequenceValue($value)
    {
        $this->sequenceValue = $value;
        return $this;
    }

    /**
     * Get the value of originalInvoicePublicId
     */
    public function getOriginalInvoicePublicId()
    {
        return $this->originalInvoicePublicId;
    }

    /**
     * Set the value of originalInvoicePublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setOriginalInvoicePublicId($value)
    {
        $this->originalInvoicePublicId = $value;
        return $this;
    }

    /**
     * Get the value of originalInvoiceNumber
     */
    public function getOriginalInvoiceNumber()
    {
        return $this->originalInvoiceNumber;
    }

    /**
     * Set the value of originalInvoiceNumber
     *
     * @param mixed $value
     * @return $this
     */
    public function setOriginalInvoiceNumber($value)
    {
        $this->originalInvoiceNumber = $value;
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
     * Get the value of reason
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set the value of reason
     *
     * @param mixed $value
     * @return $this
     */
    public function setReason($value)
    {
        $this->reason = $value;
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
