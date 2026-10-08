<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_recurring_member,repository=Flexgrid\Modules\AdminInvoice\Repository\RecurringInvoiceMemberRecordRepository,type=Module,in_menu=false,hide=true]
 * @FG\Index::tenant_plan_customer[columns={tenantId,planPublicId,customerPublicId},unique=true]
 * @FG\Index::tenant_member_due[columns={tenantId,active,nextIssueDate}]
 * @FG\Index::tenant_member_customer[columns={tenantId,customerPublicId}]
 */
final class RecurringInvoiceMemberRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $planPublicId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $customerPublicId;
    /** @FG\Column[type=varchar,length=10,required=true] */ protected $nextIssueDate;
    /** @FG\Column[type=tinyint,required=true] */ protected $active;
    /** @FG\Column[type=tinyint] */ protected $itemsInitialized;
    /** @FG\Column[type=text] */ protected $lastError;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;
    /** @FG\Column[type=bigint,required=true] */ protected $updatedAt;

    public function getId() { return $this->id; }
    public function setId($value) { $this->id = $value; return $this; }
    public function getTenantId() { return $this->tenantId; }
    public function setTenantId($value) { $this->tenantId = $value; return $this; }
    public function getPlanPublicId() { return $this->planPublicId; }
    public function setPlanPublicId($value) { $this->planPublicId = $value; return $this; }
    public function getCustomerPublicId() { return $this->customerPublicId; }
    public function setCustomerPublicId($value) { $this->customerPublicId = $value; return $this; }
    public function getNextIssueDate() { return $this->nextIssueDate; }
    public function setNextIssueDate($value) { $this->nextIssueDate = $value; return $this; }
    public function getActive() { return $this->active; }
    public function setActive($value) { $this->active = $value; return $this; }
    public function getItemsInitialized() { return $this->itemsInitialized; }
    public function setItemsInitialized($value) { $this->itemsInitialized = $value; return $this; }
    public function getLastError() { return $this->lastError; }
    public function setLastError($value) { $this->lastError = $value; return $this; }
    public function getCreatedAt() { return $this->createdAt; }
    public function setCreatedAt($value) { $this->createdAt = $value; return $this; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function setUpdatedAt($value) { $this->updatedAt = $value; return $this; }


    // --- Auto-generated getters and setters ---

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
