<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Command\CreateInvoiceCommand;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerProviderInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDraftCreatorInterface;
use Flexgrid\Modules\AdminInvoice\Contract\RecurringInvoiceLinesExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Contract\RecurringInvoiceAdditionalLinesExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

/** Plant uitsluitend controleerbare factuurconcepten; finalisatie en verzending zijn aparte acties. */
final class RecurringInvoices
{
    /** @var \PDO */ private $connection;
    /** @var TenantId */ private $tenant;
    /** @var PublicIdGeneratorInterface */ private $ids;
    /** @var InvoiceCatalog */ private $catalog;
    /** @var InvoiceDraftCreatorInterface */ private $creator;
    /** @var InvoiceCustomerProviderInterface|null */ private $customers;
    /** @var RecurringInvoiceLinesExtensionInterface|null */ private $linesExtension;
    private $onCreated;

    public function __construct(
        \PDO $connection,
        TenantId $tenant,
        PublicIdGeneratorInterface $ids,
        InvoiceCatalog $catalog,
        InvoiceDraftCreatorInterface $creator,
        ?InvoiceCustomerProviderInterface $customers,
        ?RecurringInvoiceLinesExtensionInterface $linesExtension = null,
        ?callable $onCreated = null
    ) {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->ids = $ids;
        $this->catalog = $catalog;
        $this->creator = $creator;
        $this->customers = $customers;
        $this->linesExtension = $linesExtension;
        $this->onCreated = $onCreated;
    }

    public function all(): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'ORDER BY `active` DESC,`next_issue_date` ASC,`name` ASC'
        );
        $statement->execute([':tenant' => $this->tenant->toString()]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(string $publicId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'AND `public_id`=:public_id LIMIT 1'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':public_id' => trim($publicId)]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function membersForCustomer(string $customerPublicId): array
    {
        $statement = $this->connection->prepare(
            'SELECT m.*,p.`name` AS `plan_name`,p.`active` AS `plan_active`, '
            . 'p.`catalog_item_public_id`,p.`frequency_months` FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`customer_public_id`=:customer ORDER BY p.`name`'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':customer' => trim($customerPublicId)]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function allMembers(): array
    {
        $statement = $this->connection->prepare(
            'SELECT m.*,p.`name` AS `plan_name`,p.`active` AS `plan_active`, '
            . 'p.`catalog_item_public_id` FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant'
        );
        $statement->execute([':tenant' => $this->tenant->toString()]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function allMemberItems(): array
    {
        $statement = $this->connection->prepare(
            'SELECT `plan_public_id`,`customer_public_id`,`catalog_item_public_id`,`active` '
            . 'FROM `admin_invoice_recurring_member_item` WHERE `tenant_id`=:tenant_items '
            . 'UNION ALL SELECT m.`plan_public_id`,m.`customer_public_id`, '
            . 'p.`catalog_item_public_id`,1 AS `active` FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant_members '
            . 'AND (m.`items_initialized` IS NULL OR m.`items_initialized`<>1) '
            . 'AND p.`catalog_item_public_id`<>\'\''
        );
        $statement->execute([
            ':tenant_items' => $this->tenant->toString(),
            ':tenant_members' => $this->tenant->toString(),
        ]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function memberCounts(): array
    {
        $statement = $this->connection->prepare(
            'SELECT `plan_public_id`,COUNT(*) AS `total`,SUM(CASE WHEN `active`=1 THEN 1 ELSE 0 END) '
            . 'AS `active_total`,SUM(CASE WHEN `last_error` IS NOT NULL AND `last_error`<>\'\' '
            . 'THEN 1 ELSE 0 END) AS `error_total` FROM `admin_invoice_recurring_member` WHERE `tenant_id`=:tenant '
            . 'GROUP BY `plan_public_id`'
        );
        $statement->execute([':tenant' => $this->tenant->toString()]);
        $counts = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[$row['plan_public_id']] = $row;
        }
        return $counts;
    }

    public function upcomingDateForPlan(array $plan): string
    {
        return $this->nextEnrollmentDate($plan, date('Y-m-d'));
    }

    public function itemsForCustomerPlan(string $planPublicId, string $customerPublicId): array
    {
        $member = $this->memberFor($planPublicId, $customerPublicId);
        if ($member === null) {
            throw new \InvalidArgumentException('Deze klant is niet aan dit schema gekoppeld.');
        }
        $statement = $this->connection->prepare(
            'SELECT i.*,c.`name` AS `item_name`,c.`item_type`,c.`unit_price`,c.`currency`,c.`quantity`,c.`unit` '
            . 'FROM `admin_invoice_recurring_member_item` i '
            . 'LEFT JOIN `admin_invoice_catalog_item` c ON c.`tenant_id`=i.`tenant_id` '
            . 'AND c.`public_id`=i.`catalog_item_public_id` WHERE i.`tenant_id`=:tenant '
            . 'AND i.`plan_public_id`=:plan AND i.`customer_public_id`=:customer '
            . 'ORDER BY i.`created_at`,i.`id`'
        );
        $statement->execute([
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId,
        ]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if ((int)($member['items_initialized'] ?? 0) !== 1
            && (string)$member['catalog_item_public_id'] !== '') {
            $default = $this->catalog->find((string)$member['catalog_item_public_id']);
            array_unshift($rows, [
                'catalog_item_public_id' => $member['catalog_item_public_id'],
                'item_name' => $default['name'] ?? 'Item niet beschikbaar',
                'item_type' => $default['item_type'] ?? 'item',
                'unit_price' => $default['unit_price'] ?? '',
                'currency' => $default['currency'] ?? '',
                'quantity' => $default['quantity'] ?? '',
                'unit' => $default['unit'] ?? '',
                'active' => 1,
                'legacy_default' => true,
            ]);
        }
        return $rows;
    }

    public function addItem(string $planPublicId, string $customerPublicId, string $itemPublicId): void
    {
        $member = $this->memberFor($planPublicId, $customerPublicId);
        if ($member === null) {
            throw new \InvalidArgumentException('Koppel deze klant eerst aan het herhaalschema.');
        }
        $item = $this->catalog->find($itemPublicId);
        if ($item === null || (int)$item['active'] !== 1) {
            throw new \InvalidArgumentException('Kies een actief factuuritem.');
        }
        if ($this->customers === null || ($customer = $this->customers->find($customerPublicId)) === null) {
            throw new \InvalidArgumentException('Deze klant is niet actief of heeft geen volledig factuuradres.');
        }
        $plan = $this->find($planPublicId);
        $currentItems = $this->activeMemberItems($planPublicId, $customerPublicId);
        $nextDate = $currentItems === []
            ? $this->nextEnrollmentDate($plan, max(date('Y-m-d'), (string)$member['next_issue_date']))
            : (string)$member['next_issue_date'];
        $validation = $plan;
        $validation['catalog_item_public_id'] = $itemPublicId;
        $validation['next_issue_date'] = $nextDate;
        $validation['assigned_item_public_ids'] = array_merge(
            array_column($currentItems, 'catalog_item_public_id'), [$itemPublicId]
        );
        if ($this->catalog->isGroup($item) && $this->catalog->children($itemPublicId) === []) {
            throw new \InvalidArgumentException('Voeg eerst actieve varianten toe aan deze keuzegroep.');
        }
        if (!$this->catalog->isGroup($item) && $this->linesExtension !== null) {
            $this->linesExtension->validate($validation, $customer, $this->catalog);
        }
        foreach ($this->itemsForCustomerPlan($planPublicId, $customerPublicId) as $existing) {
            if ($existing['catalog_item_public_id'] === $itemPublicId && (int)$existing['active'] === 1) {
                throw new \InvalidArgumentException('Dit factuuritem staat al bij dit schema.');
            }
            if ((int)$existing['active'] === 1 && $existing['currency'] !== $item['currency']) {
                throw new \InvalidArgumentException('Items op één factuur moeten dezelfde valuta hebben.');
            }
        }
        $this->assertNoCompetingItem($planPublicId, $customerPublicId, $itemPublicId);
        $this->initializeItems($member);
        $this->upsertItem($planPublicId, $customerPublicId, $itemPublicId);
        if ($currentItems === []) {
            $this->updateMemberDateAfterFirstItem($planPublicId, $customerPublicId, $nextDate);
        }
    }

    public function setItemActive(
        string $planPublicId, string $customerPublicId, string $itemPublicId, bool $active
    ): void {
        $member = $this->memberFor($planPublicId, $customerPublicId);
        if ($member === null) {
            throw new \InvalidArgumentException('Deze klant is niet aan dit schema gekoppeld.');
        }
        $known = false;
        foreach ($this->itemsForCustomerPlan($planPublicId, $customerPublicId) as $row) {
            if ($row['catalog_item_public_id'] === $itemPublicId) {
                $known = true;
                break;
            }
        }
        if (!$known) {
            throw new \InvalidArgumentException('Dit factuuritem staat niet bij dit schema.');
        }
        if ($active) {
            $item = $this->catalog->find($itemPublicId);
            if ($item === null || (int)$item['active'] !== 1) {
                throw new \InvalidArgumentException('Dit factuuritem is niet meer actief.');
            }
            if ($this->catalog->isGroup($item) && $this->catalog->children($itemPublicId) === []) {
                throw new \InvalidArgumentException('Deze keuzegroep heeft geen actieve varianten.');
            }
            if ($this->customers === null || ($customer = $this->customers->find($customerPublicId)) === null) {
                throw new \InvalidArgumentException('Deze klant is niet actief of heeft geen volledig factuuradres.');
            }
            $plan = $this->find($planPublicId);
            $otherActiveItems = $this->activeMemberItems($planPublicId, $customerPublicId);
            $nextDate = $otherActiveItems === []
                ? $this->nextEnrollmentDate($plan, max(date('Y-m-d'), (string)$member['next_issue_date']))
                : (string)$member['next_issue_date'];
            if (!$this->catalog->isGroup($item) && $this->linesExtension !== null) {
                $validation = $plan;
                $validation['catalog_item_public_id'] = $itemPublicId;
                $validation['next_issue_date'] = $nextDate;
                $validation['assigned_item_public_ids'] = array_merge(
                    array_column($otherActiveItems, 'catalog_item_public_id'),
                    [$itemPublicId]
                );
                $this->linesExtension->validate($validation, $customer, $this->catalog);
            }
            foreach ($this->itemsForCustomerPlan($planPublicId, $customerPublicId) as $other) {
                if ($other['catalog_item_public_id'] !== $itemPublicId
                    && (int)$other['active'] === 1 && $other['currency'] !== $item['currency']) {
                    throw new \InvalidArgumentException('Items op één factuur moeten dezelfde valuta hebben.');
                }
            }
            $this->assertNoCompetingItem($planPublicId, $customerPublicId, $itemPublicId);
        }
        $this->initializeItems($member);
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_recurring_member_item` SET `active`=:active,`updated_at`=:updated_at '
            . 'WHERE `tenant_id`=:tenant AND `plan_public_id`=:plan AND `customer_public_id`=:customer '
            . 'AND `catalog_item_public_id`=:item'
        );
        $statement->execute([
            ':active' => $active ? 1 : 0, ':updated_at' => time(),
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId, ':item' => $itemPublicId,
        ]);
        if ($active && $otherActiveItems === []) {
            $this->updateMemberDateAfterFirstItem($planPublicId, $customerPublicId, $nextDate);
        }
    }

    public function enroll(string $planPublicId, string $customerPublicId): void
    {
        $plan = $this->find($planPublicId);
        if ($plan === null || (string)$plan['customer_public_id'] !== '') {
            throw new \InvalidArgumentException('Kies een gedeeld herhaalschema.');
        }
        if ((int)$plan['active'] !== 1) {
            throw new \InvalidArgumentException('Activeer eerst het herhaalschema.');
        }
        if ($this->customers === null || ($customer = $this->customers->find($customerPublicId)) === null) {
            throw new \InvalidArgumentException('Deze klant is niet actief of heeft geen volledig factuuradres.');
        }
        $defaultItemId = (string)$plan['catalog_item_public_id'];
        if ($defaultItemId !== '') {
            $item = $this->catalog->find($defaultItemId);
            if ($item === null || (int)$item['active'] !== 1) {
                throw new \InvalidArgumentException('Het factuuritem van dit schema is niet actief.');
            }
            $this->assertNoCompetingItem($planPublicId, $customerPublicId, $defaultItemId);
        }
        foreach ($this->membersForCustomer($customerPublicId) as $membership) {
            if ($membership['plan_public_id'] === $planPublicId) {
                throw new \InvalidArgumentException('Deze klant is al aan dit schema gekoppeld.');
            }
        }
        $nextDate = $this->nextEnrollmentDate($plan, date('Y-m-d'));
        if ($defaultItemId !== '' && $this->linesExtension !== null) {
            $validation = $plan;
            $validation['next_issue_date'] = $nextDate;
            $this->linesExtension->validate($validation, $customer, $this->catalog);
        }
        $now = time();
        $statement = $this->connection->prepare(
            'INSERT INTO `admin_invoice_recurring_member` (`tenant_id`,`plan_public_id`, '
            . '`customer_public_id`,`next_issue_date`,`active`,`items_initialized`, '
            . '`last_error`,`created_at`,`updated_at`) '
            . 'VALUES (:tenant,:plan,:customer,:next_date,1,:initialized,NULL,:created_at,:updated_at)'
        );
        $statement->execute([
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId, ':next_date' => $nextDate,
            ':initialized' => $defaultItemId === '' ? 1 : 0,
            ':created_at' => $now, ':updated_at' => $now,
        ]);
    }

    public function unlinkCustomer(string $planPublicId, string $customerPublicId): void
    {
        $plan = $this->find($planPublicId);
        if ($plan === null || (string)$plan['customer_public_id'] !== '') {
            throw new \InvalidArgumentException('Kies een gedeeld herhaalschema.');
        }
        if ($this->memberFor($planPublicId, $customerPublicId) === null) {
            throw new \InvalidArgumentException('Deze klant is niet aan dit schema gekoppeld.');
        }
        $params = [
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId,
        ];
        $this->connection->beginTransaction();
        try {
            $items = $this->connection->prepare(
                'DELETE FROM `admin_invoice_recurring_member_item` WHERE `tenant_id`=:tenant '
                . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
            );
            $items->execute($params);
            $member = $this->connection->prepare(
                'DELETE FROM `admin_invoice_recurring_member` WHERE `tenant_id`=:tenant '
                . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
            );
            $member->execute($params);
            if ($member->rowCount() !== 1) {
                throw new \RuntimeException('De klantkoppeling kon niet worden verwijderd.');
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function setMemberActive(string $planPublicId, string $customerPublicId, bool $active): void
    {
        $member = null;
        foreach ($this->membersForCustomer($customerPublicId) as $row) {
            if ($row['plan_public_id'] === $planPublicId) {
                $member = $row;
                break;
            }
        }
        if ($member === null) {
            throw new \InvalidArgumentException('Deze klant is niet aan dit schema gekoppeld.');
        }
        if ((int)$member['active'] === ($active ? 1 : 0)) {
            return;
        }
        $nextDate = (string)$member['next_issue_date'];
        if ($active) {
            $plan = $this->find($planPublicId);
            if ($plan === null || (int)$plan['active'] !== 1) {
                throw new \InvalidArgumentException('Activeer eerst het herhaalschema.');
            }
            if ($this->customers === null || ($customer = $this->customers->find($customerPublicId)) === null) {
                throw new \InvalidArgumentException('Deze klant is niet actief of heeft geen volledig factuuradres.');
            }
            $activeItems = $this->activeMemberItems($planPublicId, $customerPublicId);
            foreach ($activeItems as $assignedItem) {
                $this->assertNoCompetingItem(
                    $planPublicId, $customerPublicId, (string)$assignedItem['catalog_item_public_id']
                );
            }
            $nextDate = $this->nextEnrollmentDate(
                $plan, max(date('Y-m-d'), (string)$member['next_issue_date'])
            );
            if ($this->linesExtension !== null) {
                $assignedIds = array_column($activeItems, 'catalog_item_public_id');
                foreach ($activeItems as $assignedItem) {
                    $validation = $plan;
                    $validation['catalog_item_public_id'] = $assignedItem['catalog_item_public_id'];
                    $validation['next_issue_date'] = $nextDate;
                    $validation['assigned_item_public_ids'] = $assignedIds;
                    $this->linesExtension->validate($validation, $customer, $this->catalog);
                }
            }
        }
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_recurring_member` SET `active`=:active,`next_issue_date`=:next_date, '
            . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
            . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
        );
        $statement->execute([
            ':active' => $active ? 1 : 0, ':next_date' => $nextDate, ':updated_at' => time(),
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId, ':customer' => $customerPublicId,
        ]);
    }

    public function setActive(string $publicId, bool $active): void
    {
        $plan = $this->find($publicId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Dit herhaalschema bestaat niet.');
        }
        if ((int)$plan['active'] === ($active ? 1 : 0)) {
            return;
        }
        if ($active && (string)$plan['customer_public_id'] === '') {
            foreach ($this->membersForPlan($publicId) as $member) {
                if ((int)$member['active'] === 1) {
                    $this->assertNoCompetingPlan($plan, (string)$member['customer_public_id']);
                }
            }
        }
        if ($active && (string)$plan['customer_public_id'] !== '') {
            $this->assertNoCompetingPlan($plan, (string)$plan['customer_public_id']);
        }
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_recurring` SET `active`=:active,`updated_at`=:updated_at '
            . 'WHERE `tenant_id`=:tenant AND `public_id`=:public_id'
        );
        $statement->execute([
            ':active' => $active ? 1 : 0, ':updated_at' => time(),
            ':tenant' => $this->tenant->toString(), ':public_id' => $publicId,
        ]);
        if ($active && (string)$plan['customer_public_id'] === '') {
            foreach ($this->membersForPlan($publicId) as $member) {
                if ((int)$member['active'] !== 1) {
                    continue;
                }
                $nextDate = $this->nextEnrollmentDate(
                    $plan, max(date('Y-m-d'), (string)$member['next_issue_date'])
                );
                $update = $this->connection->prepare(
                    'UPDATE `admin_invoice_recurring_member` SET `next_issue_date`=:next_date, '
                    . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
                    . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer AND `active`=1'
                );
                $update->execute([
                    ':next_date' => $nextDate, ':updated_at' => time(),
                    ':tenant' => $this->tenant->toString(), ':plan' => $publicId,
                    ':customer' => $member['customer_public_id'],
                ]);
            }
        }
    }

    public function save(array $input): string
    {
        if ($this->customers === null) {
            throw new \LogicException('Koppel eerst een klantprovider voor terugkerende facturen.');
        }
        $publicId = trim((string)($input['public_id'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $customerId = trim((string)($input['customer_public_id'] ?? ''));
        $itemId = trim((string)($input['catalog_item_public_id'] ?? ''));
        $nextDate = trim((string)($input['next_issue_date'] ?? ''));
        $frequency = (int)($input['frequency_months'] ?? 1);
        $dueDays = (int)($input['due_days'] ?? 14);
        $active = !empty($input['active']) ? 1 : 0;
        if ($name === '' || strlen($name) > 255) {
            throw new \InvalidArgumentException('Geef het schema een herkenbare naam.');
        }
        new CalendarDate($nextDate);
        if (!in_array($frequency, [1, 3, 12], true) || $dueDays < 0 || $dueDays > 365) {
            throw new \InvalidArgumentException('Kies maandelijks, per kwartaal of jaarlijks en een geldige betaaltermijn.');
        }
        $existing = $publicId !== '' ? $this->find($publicId) : null;
        if ($publicId !== '' && $existing === null) {
            throw new \InvalidArgumentException('Dit herhaalschema bestaat niet.');
        }
        if ($existing !== null && (($existing['customer_public_id'] === '') !== ($customerId === ''))) {
            throw new \InvalidArgumentException('Een bestaand schema kan niet van type veranderen.');
        }
        $customer = $customerId !== '' ? $this->customers->find($customerId) : null;
        if ($customerId !== '' && $customer === null) {
            throw new \InvalidArgumentException('Kies een actieve klant met een volledig factuuradres.');
        }
        $item = $itemId !== '' ? $this->catalog->find($itemId) : null;
        if (($customerId !== '' || $itemId !== '') && ($item === null || (int)$item['active'] !== 1)) {
            throw new \InvalidArgumentException('Kies een actief catalogusitem.');
        }
        if ($customer !== null && $this->linesExtension !== null) {
            $this->linesExtension->validate([
                'catalog_item_public_id' => $itemId,
                'frequency_months' => $frequency,
                'next_issue_date' => $nextDate,
            ], $customer, $this->catalog);
        }
        if ($customer !== null && $active === 1) {
            $this->assertNoCompetingPlan([
                'public_id' => $publicId,
                'catalog_item_public_id' => $itemId,
            ], $customerId);
        }
        $now = time();
        if ($publicId === '') {
            $publicId = $this->ids->generate();
            $statement = $this->connection->prepare(
                'INSERT INTO `admin_invoice_recurring` '
                . '(`tenant_id`,`public_id`,`name`,`customer_public_id`,`catalog_item_public_id`, '
                . '`next_issue_date`,`day_of_month`,`frequency_months`,`due_days`,`active`, '
                . '`last_error`,`created_at`,`updated_at`) VALUES '
                . '(:tenant,:public_id,:name,:customer,:item,:next_date,:day,:frequency,:due_days, '
                . ':active,NULL,:created_at,:updated_at)'
            );
            $statement->execute([
                ':tenant' => $this->tenant->toString(), ':public_id' => $publicId,
                ':name' => $name, ':customer' => $customerId, ':item' => $itemId,
                ':next_date' => $nextDate, ':day' => (int)substr($nextDate, 8, 2),
                ':frequency' => $frequency, ':due_days' => $dueDays, ':active' => $active,
                ':created_at' => $now, ':updated_at' => $now,
            ]);
            return $publicId;
        }
        if ($customerId === '' && $existing !== null) {
            if ($existing['catalog_item_public_id'] !== $itemId && $this->membersForPlan($publicId) !== []) {
                if ($itemId !== '' || $existing['catalog_item_public_id'] === '') {
                    throw new \InvalidArgumentException(
                        'Items van een gedeeld schema beheer je per klant. Kies daar een ander factuuritem.'
                    );
                }
                foreach ($this->membersForPlan($publicId) as $member) {
                    $this->initializeItems($this->memberFor(
                        $publicId, (string)$member['customer_public_id']
                    ));
                }
            }
            $changedSchedule = $existing['next_issue_date'] !== $nextDate
                || (int)$existing['frequency_months'] !== $frequency;
            if ($changedSchedule) {
                $members = $this->membersForPlan($publicId);
                foreach ($members as $member) {
                    if ((int)$member['active'] !== 1) {
                        continue;
                    }
                    $candidate = [
                        'next_issue_date' => $nextDate,
                        'day_of_month' => (int)substr($nextDate, 8, 2),
                        'frequency_months' => $frequency,
                    ];
                    $memberDate = $this->nextEnrollmentDate($candidate, date('Y-m-d'));
                    if ($this->linesExtension !== null) {
                        $memberCustomer = $this->customers->find((string)$member['customer_public_id']);
                        if ($memberCustomer !== null) {
                            $assignedItems = $this->activeMemberItems(
                                $publicId, (string)$member['customer_public_id']
                            );
                            $assignedIds = array_column($assignedItems, 'catalog_item_public_id');
                            foreach ($assignedItems as $assignedItem) {
                                $this->linesExtension->validate([
                                    'catalog_item_public_id' => $assignedItem['catalog_item_public_id'],
                                    'frequency_months' => $frequency,
                                    'next_issue_date' => $memberDate,
                                    'assigned_item_public_ids' => $assignedIds,
                                ], $memberCustomer, $this->catalog);
                            }
                        }
                    }
                }
            }
        }
        if ($customerId === '' && $existing !== null && (int)$existing['active'] === 0 && $active === 1) {
            $candidatePlan = $existing;
            $candidatePlan['catalog_item_public_id'] = $itemId;
            foreach ($this->membersForPlan($publicId) as $member) {
                if ((int)$member['active'] === 1) {
                    $this->assertNoCompetingPlan($candidatePlan, (string)$member['customer_public_id']);
                }
            }
        }
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_recurring` SET `name`=:name,`customer_public_id`=:customer, '
            . '`catalog_item_public_id`=:item,`next_issue_date`=:next_date,`day_of_month`=:day, '
            . '`frequency_months`=:frequency,`due_days`=:due_days,`active`=:active, '
            . '`last_error`=NULL,`updated_at`=:updated_at '
            . 'WHERE `tenant_id`=:tenant AND `public_id`=:public_id'
        );
        $statement->execute([
            ':name' => $name, ':customer' => $customerId, ':item' => $itemId,
            ':next_date' => $nextDate, ':day' => (int)substr($nextDate, 8, 2),
            ':frequency' => $frequency, ':due_days' => $dueDays, ':active' => $active,
            ':updated_at' => $now, ':tenant' => $this->tenant->toString(), ':public_id' => $publicId,
        ]);
        if ($customerId === '' && isset($changedSchedule) && $changedSchedule) {
            $members = $this->membersForPlan($publicId);
            foreach ($members as $member) {
                if ((int)$member['active'] !== 1) {
                    continue;
                }
                $nextMemberDate = $this->nextEnrollmentDate([
                    'next_issue_date' => $nextDate,
                    'day_of_month' => (int)substr($nextDate, 8, 2),
                    'frequency_months' => $frequency,
                ], date('Y-m-d'));
                $updateMember = $this->connection->prepare(
                    'UPDATE `admin_invoice_recurring_member` SET `next_issue_date`=:next_date, '
                    . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
                    . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
                );
                $updateMember->execute([
                    ':next_date' => $nextMemberDate, ':updated_at' => $now,
                    ':tenant' => $this->tenant->toString(), ':plan' => $publicId,
                    ':customer' => $member['customer_public_id'],
                ]);
            }
        }
        return $publicId;
    }

    public function runDue(string $today, int $limit = 100): array
    {
        new CalendarDate($today);
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Ongeldige verwerkingslimiet.');
        }
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'AND `active`=1 AND `customer_public_id`<>\'\' AND `next_issue_date`<=:today '
            . 'ORDER BY `next_issue_date`,`id` LIMIT ' . $limit
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':today' => $today]);
        $report = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $processed = 0;
            while ($plan['next_issue_date'] <= $today && $processed < 12) {
                try {
                    $made = $this->createDraft($plan);
                    $next = $this->nextDate(
                        (string)$plan['next_issue_date'], (int)$plan['day_of_month'],
                        (int)$plan['frequency_months']
                    );
                    $update = $this->connection->prepare(
                        'UPDATE `admin_invoice_recurring` SET `next_issue_date`=:next_date, '
                        . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
                        . 'AND `public_id`=:public_id AND `next_issue_date`=:old_date AND `active`=1'
                    );
                    $update->execute([
                        ':next_date' => $next, ':updated_at' => time(),
                        ':tenant' => $this->tenant->toString(), ':public_id' => $plan['public_id'],
                        ':old_date' => $plan['next_issue_date'],
                    ]);
                    if ($update->rowCount() !== 1) {
                        break;
                    }
                    $plan['next_issue_date'] = $next;
                    $report[$made ? 'created' : 'skipped']++;
                    $processed++;
                } catch (\Throwable $exception) {
                    $message = substr($exception->getMessage(), 0, 1000);
                    $error = $this->connection->prepare(
                        'UPDATE `admin_invoice_recurring` SET `last_error`=:error,`updated_at`=:updated_at '
                        . 'WHERE `tenant_id`=:tenant AND `public_id`=:public_id'
                    );
                    $error->execute([
                        ':error' => $message, ':updated_at' => time(),
                        ':tenant' => $this->tenant->toString(), ':public_id' => $plan['public_id'],
                    ]);
                    $report['failed']++;
                    $report['errors'][] = ['name' => $plan['name'], 'message' => $message];
                    break;
                }
            }
        }
        $memberStatement = $this->connection->prepare(
            'SELECT m.`customer_public_id` AS `member_customer_public_id`, '
            . 'm.`next_issue_date` AS `member_next_issue_date`, '
            . 'p.* FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`active`=1 AND p.`active`=1 AND p.`customer_public_id`=\'\' '
            . 'AND m.`next_issue_date`<=:today '
            . 'ORDER BY m.`next_issue_date`,m.`id` LIMIT ' . $limit
        );
        $memberStatement->execute([':tenant' => $this->tenant->toString(), ':today' => $today]);
        foreach ($memberStatement->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $customerId = (string)$plan['member_customer_public_id'];
            $plan['next_issue_date'] = $plan['member_next_issue_date'];
            $processed = 0;
            while ($plan['next_issue_date'] <= $today && $processed < 12) {
                try {
                    $made = $this->createDraft($plan, $customerId);
                    $next = $this->nextDate(
                        (string)$plan['next_issue_date'], (int)$plan['day_of_month'],
                        (int)$plan['frequency_months']
                    );
                    $update = $this->connection->prepare(
                        'UPDATE `admin_invoice_recurring_member` SET `next_issue_date`=:next_date, '
                        . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
                        . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer '
                        . 'AND `next_issue_date`=:old_date AND `active`=1'
                    );
                    $update->execute([
                        ':next_date' => $next, ':updated_at' => time(),
                        ':tenant' => $this->tenant->toString(), ':plan' => $plan['public_id'],
                        ':customer' => $customerId, ':old_date' => $plan['next_issue_date'],
                    ]);
                    if ($update->rowCount() !== 1) {
                        break;
                    }
                    $plan['next_issue_date'] = $next;
                    $report[$made ? 'created' : 'skipped']++;
                    $processed++;
                } catch (\Throwable $exception) {
                    $message = substr($exception->getMessage(), 0, 1000);
                    $error = $this->connection->prepare(
                        'UPDATE `admin_invoice_recurring_member` SET `last_error`=:error, '
                        . '`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
                        . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
                    );
                    $error->execute([
                        ':error' => $message, ':updated_at' => time(),
                        ':tenant' => $this->tenant->toString(), ':plan' => $plan['public_id'],
                        ':customer' => $customerId,
                    ]);
                    $report['failed']++;
                    $report['errors'][] = ['name' => $plan['name'] . ' / ' . $customerId, 'message' => $message];
                    break;
                }
            }
        }
        return $report;
    }

    /** Read-only version of the due run. It resolves the same customer items and project callbacks. */
    public function previewDue(string $today, int $limit = 100): array
    {
        new CalendarDate($today);
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Ongeldige verwerkingslimiet.');
        }
        $rows = [];
        $legacy = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'AND `active`=1 AND `customer_public_id`<>\'\' AND `next_issue_date`<=:today '
            . 'ORDER BY `next_issue_date`,`id` LIMIT ' . $limit
        );
        $legacy->execute([':tenant' => $this->tenant->toString(), ':today' => $today]);
        foreach ($legacy->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $this->previewPeriods($rows, $plan, '', $today);
        }
        $members = $this->connection->prepare(
            'SELECT m.`customer_public_id` AS `member_customer_public_id`, '
            . 'm.`next_issue_date` AS `member_next_issue_date`, p.* '
            . 'FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`active`=1 AND p.`active`=1 AND p.`customer_public_id`=\'\' '
            . 'AND m.`next_issue_date`<=:today '
            . 'ORDER BY m.`next_issue_date`,m.`id` LIMIT ' . $limit
        );
        $members->execute([':tenant' => $this->tenant->toString(), ':today' => $today]);
        foreach ($members->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $plan['next_issue_date'] = $plan['member_next_issue_date'];
            $this->previewPeriods($rows, $plan, (string)$plan['member_customer_public_id'], $today);
        }
        $counts = ['ready' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }
        return ['date' => $today, 'rows' => $rows, 'counts' => $counts];
    }

    private function previewPeriods(array &$rows, array $plan, string $customerId, string $today): void
    {
        $periods = 0;
        while ($plan['next_issue_date'] <= $today && $periods++ < 12) {
            $row = [
                'plan_name' => (string)$plan['name'],
                'customer_id' => $customerId !== '' ? $customerId : (string)$plan['customer_public_id'],
                'customer_name' => '',
                'issue_date' => (string)$plan['next_issue_date'],
                'status' => 'ready',
                'lines' => [],
                'gross' => '',
                'currency' => '',
                'message' => '',
            ];
            try {
                if ($this->customers !== null) {
                    $selection = $this->customers->find($row['customer_id']);
                    if ($selection !== null) {
                        $row['customer_name'] = (string)($selection->getParty()['name'] ?? '');
                    }
                }
                $command = $this->prepareDraft($plan, $customerId);
                if ($command === null) {
                    $row['status'] = 'skipped';
                    $row['message'] = 'Geen regels voor deze periode.';
                } else {
                    $row['customer_name'] = (string)($command->getCustomerSnapshot()['name'] ?? '');
                    $row['currency'] = $command->getCurrency();
                    $row['lines'] = array_column($command->getLines(), 'description');
                    $row['gross'] = $this->previewGross($command);
                }
            } catch (\Throwable $exception) {
                $row['status'] = 'failed';
                $row['message'] = $exception->getMessage();
            }
            $rows[] = $row;
            if ($row['status'] === 'failed') {
                break;
            }
            $plan['next_issue_date'] = $this->nextDate(
                (string)$plan['next_issue_date'], (int)$plan['day_of_month'],
                (int)$plan['frequency_months']
            );
        }
    }

    private function previewGross(CreateInvoiceCommand $command): string
    {
        $currency = new Currency($command->getCurrency());
        $gross = Money::zero($currency);
        foreach ($command->getLines() as $index => $line) {
            $invoiceLine = new InvoiceLine(
                'preview-' . $index,
                (string)$line['description'],
                Quantity::fromDecimal((string)$line['quantity']),
                (string)$line['unit'],
                Money::fromDecimal((string)$line['unit_price'], $currency),
                TaxRate::fromPercentage((string)$line['tax_rate']),
                new TaxCategory((string)$line['tax_category']),
                ($index + 1) * 10
            );
            $gross = $gross->add($invoiceLine->calculateGross());
        }
        return $gross->format();
    }

    private function createDraft(array $plan, string $memberCustomerId = ''): bool
    {
        $command = $this->prepareDraft($plan, $memberCustomerId);
        if ($command === null) {
            return false;
        }
        $invoice = $this->creator->execute($command);
        if ($this->onCreated !== null) { ($this->onCreated)($invoice); }
        return true;
    }

    private function prepareDraft(array $plan, string $memberCustomerId = ''): ?CreateInvoiceCommand
    {
        if ($this->customers === null) {
            throw new \LogicException('Er is geen klantprovider beschikbaar.');
        }
        $customer = $this->customers->find($memberCustomerId !== ''
            ? $memberCustomerId : (string)$plan['customer_public_id']);
        if ($customer === null) {
            throw new \InvalidArgumentException('De klant is niet meer actief of het factuuradres ontbreekt.');
        }
        $issueDate = (string)$plan['next_issue_date'];
        $assignedItems = $memberCustomerId !== ''
            ? $this->activeMemberItems((string)$plan['public_id'], $memberCustomerId)
            : [['catalog_item_public_id' => $plan['catalog_item_public_id']]];
        if ($assignedItems === [] && !($this->linesExtension instanceof RecurringInvoiceAdditionalLinesExtensionInterface)) {
            throw new \InvalidArgumentException('Voeg eerst minstens één actief factuuritem toe aan deze klantkoppeling.');
        }
        $currency = '';
        $lines = [];
        $assignedIds = array_column($assignedItems, 'catalog_item_public_id');
        foreach ($assignedItems as $assignedItem) {
            $itemId = (string)$assignedItem['catalog_item_public_id'];
            $item = $this->catalog->find($itemId);
            if ($item === null || (int)$item['active'] !== 1) {
                throw new \InvalidArgumentException('Een gekoppeld factuuritem is niet meer actief.');
            }
            $isGroup = $this->catalog->isGroup($item);
            $choice = $isGroup ? $this->catalog->choose($item, $customer, $issueDate) : null;
            if ($choice === false) {
                continue;
            }
            if ($isGroup) {
                $itemId = (string)$choice['catalog_item_public_id'];
                $item = $this->catalog->find($itemId);
            }
            if ($currency === '') {
                $currency = (string)$item['currency'];
            } elseif ($currency !== $item['currency']) {
                throw new \InvalidArgumentException('Items op één factuur moeten dezelfde valuta hebben.');
            }
            $itemPlan = $plan;
            $itemPlan['catalog_item_public_id'] = $itemId;
            $itemPlan['assigned_item_public_ids'] = $assignedIds;
            $itemLines = !$isGroup && $this->linesExtension !== null
                ? $this->linesExtension->resolve($itemPlan, $customer, $this->catalog, $issueDate)
                : null;
            if ($itemLines === null) {
                $line = $this->catalog->line($itemId, $currency);
                $line['catalog_item_public_id'] = $itemId;
                if ($isGroup && isset($choice['quantity'])) {
                    $line['quantity'] = $choice['quantity'];
                }
                $period = date('m-Y', strtotime($issueDate));
                $line['description'] = strpos($line['description'], '{periode}') !== false
                    ? str_replace('{periode}', $period, $line['description'])
                    : $line['description'] . ' - ' . $period;
                $itemLines = [$line];
            }
            foreach ($itemLines as $line) {
                $lines[] = $line;
            }
        }
        if ($this->linesExtension instanceof RecurringInvoiceAdditionalLinesExtensionInterface) {
            $additional = $this->linesExtension->additionalLines(
                $plan, $customer, $this->catalog, $issueDate, $assignedIds, $lines
            );
            if ($additional !== []) {
                $extraCurrency = (string)($additional['currency'] ?? '');
                $extraLines = $additional['lines'] ?? null;
                if ($extraCurrency === '' || !is_array($extraLines)) {
                    throw new \UnexpectedValueException('Extra factuurregels moeten een valuta en regels bevatten.');
                }
                if ($extraLines !== []) {
                    if ($currency !== '' && $currency !== $extraCurrency) {
                        throw new \InvalidArgumentException('Extra factuurregels moeten dezelfde valuta hebben.');
                    }
                    $currency = $extraCurrency;
                    foreach ($extraLines as $line) {
                        if (!is_array($line)) {
                            throw new \UnexpectedValueException('Iedere extra factuurregel moet een array zijn.');
                        }
                        $lines[] = $line;
                    }
                }
            }
        }
        if ($lines === []) {
            return null;
        }
        $party = $customer->getParty();
        $party['source_public_id'] = $customer->getPublicId();
        $dueDate = (new \DateTimeImmutable($issueDate))
            ->modify('+' . (int)$plan['due_days'] . ' days')->format('Y-m-d');
        return new CreateInvoiceCommand(
            $currency, $issueDate, $dueDate,
            $party, $customer->getBillingAddress(), $lines, '', '',
            'admin_recurring', (string)$plan['public_id'] . ':'
                . ($memberCustomerId !== '' ? $memberCustomerId . ':' : '') . $issueDate
        );
    }

    public function membersForPlan(string $planPublicId): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_recurring_member` WHERE `tenant_id`=:tenant '
            . 'AND `plan_public_id`=:plan ORDER BY `customer_public_id`'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':plan' => $planPublicId]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function memberFor(string $planPublicId, string $customerPublicId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT m.*,p.`catalog_item_public_id`,p.`frequency_months`,p.`active` AS `plan_active` '
            . 'FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`plan_public_id`=:plan AND m.`customer_public_id`=:customer LIMIT 1'
        );
        $statement->execute([
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function activeMemberItems(string $planPublicId, string $customerPublicId): array
    {
        return array_values(array_filter(
            $this->itemsForCustomerPlan($planPublicId, $customerPublicId),
            static function (array $row): bool { return (int)$row['active'] === 1; }
        ));
    }

    private function initializeItems(array $member): void
    {
        if ((int)($member['items_initialized'] ?? 0) === 1) {
            return;
        }
        $this->connection->beginTransaction();
        try {
            if ((string)$member['catalog_item_public_id'] !== '') {
                $this->upsertItem(
                    (string)$member['plan_public_id'], (string)$member['customer_public_id'],
                    (string)$member['catalog_item_public_id']
                );
            }
            $statement = $this->connection->prepare(
                'UPDATE `admin_invoice_recurring_member` SET `items_initialized`=1,`updated_at`=:updated_at '
                . 'WHERE `tenant_id`=:tenant AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
            );
            $statement->execute([
                ':updated_at' => time(), ':tenant' => $this->tenant->toString(),
                ':plan' => $member['plan_public_id'], ':customer' => $member['customer_public_id'],
            ]);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function upsertItem(string $planPublicId, string $customerPublicId, string $itemPublicId): void
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring_member_item` WHERE `tenant_id`=:tenant '
            . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer '
            . 'AND `catalog_item_public_id`=:item LIMIT 1'
        );
        $params = [
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId, ':item' => $itemPublicId,
        ];
        $statement->execute($params);
        if ($statement->fetchColumn()) {
            $update = $this->connection->prepare(
                'UPDATE `admin_invoice_recurring_member_item` SET `active`=1,`updated_at`=:updated_at '
                . 'WHERE `tenant_id`=:tenant AND `plan_public_id`=:plan '
                . 'AND `customer_public_id`=:customer AND `catalog_item_public_id`=:item'
            );
            $update->execute($params + [':updated_at' => time()]);
            return;
        }
        $insert = $this->connection->prepare(
            'INSERT INTO `admin_invoice_recurring_member_item` '
            . '(`tenant_id`,`plan_public_id`,`customer_public_id`,`catalog_item_public_id`, '
            . '`active`,`created_at`,`updated_at`) VALUES '
            . '(:tenant,:plan,:customer,:item,1,:created_at,:updated_at)'
        );
        $insert->execute($params + [':created_at' => time(), ':updated_at' => time()]);
    }

    private function updateMemberDateAfterFirstItem(
        string $planPublicId, string $customerPublicId, string $nextDate
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_recurring_member` SET `next_issue_date`=:next_date, '
            . '`last_error`=NULL,`updated_at`=:updated_at WHERE `tenant_id`=:tenant '
            . 'AND `plan_public_id`=:plan AND `customer_public_id`=:customer'
        );
        $statement->execute([
            ':next_date' => $nextDate, ':updated_at' => time(),
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId,
        ]);
    }

    private function assertNoCompetingItem(
        string $planPublicId, string $customerPublicId, string $itemPublicId
    ): void {
        $legacy = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'AND `public_id`<>:plan AND `customer_public_id`=:customer '
            . 'AND `catalog_item_public_id`=:item AND `active`=1 LIMIT 1'
        );
        $params = [
            ':tenant' => $this->tenant->toString(), ':plan' => $planPublicId,
            ':customer' => $customerPublicId, ':item' => $itemPublicId,
        ];
        $legacy->execute($params);
        if ($legacy->fetchColumn()) {
            throw new \InvalidArgumentException('Dit item staat al op een actief individueel schema van deze klant.');
        }
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring_member_item` i '
            . 'INNER JOIN `admin_invoice_recurring_member` m ON m.`tenant_id`=i.`tenant_id` '
            . 'AND m.`plan_public_id`=i.`plan_public_id` AND m.`customer_public_id`=i.`customer_public_id` '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=i.`tenant_id` '
            . 'AND p.`public_id`=i.`plan_public_id` WHERE i.`tenant_id`=:tenant '
            . 'AND i.`plan_public_id`<>:plan AND i.`customer_public_id`=:customer '
            . 'AND i.`catalog_item_public_id`=:item AND i.`active`=1 AND m.`active`=1 '
            . 'AND p.`active`=1 LIMIT 1'
        );
        $statement->execute($params);
        if ($statement->fetchColumn()) {
            throw new \InvalidArgumentException('Dit item staat al op een ander actief schema van deze klant.');
        }
        $fallback = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`plan_public_id`<>:plan AND m.`customer_public_id`=:customer '
            . 'AND (m.`items_initialized` IS NULL OR m.`items_initialized`<>1) '
            . 'AND p.`catalog_item_public_id`=:item AND m.`active`=1 AND p.`active`=1 LIMIT 1'
        );
        $fallback->execute($params);
        if ($fallback->fetchColumn()) {
            throw new \InvalidArgumentException('Dit item staat al op een ander actief schema van deze klant.');
        }
    }

    private function assertNoCompetingPlan(array $plan, string $customerPublicId): void
    {
        if ((string)($plan['customer_public_id'] ?? 'legacy') === '') {
            foreach ($this->activeMemberItems((string)$plan['public_id'], $customerPublicId) as $assignedItem) {
                $this->assertNoCompetingItem(
                    (string)$plan['public_id'], $customerPublicId,
                    (string)$assignedItem['catalog_item_public_id']
                );
            }
            return;
        }
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant '
            . 'AND `public_id`<>:plan AND `customer_public_id`=:customer '
            . 'AND `catalog_item_public_id`=:item AND `active`=1 LIMIT 1'
        );
        $statement->execute([
            ':tenant' => $this->tenant->toString(), ':plan' => $plan['public_id'],
            ':customer' => $customerPublicId, ':item' => $plan['catalog_item_public_id'],
        ]);
        if ($statement->fetchColumn()) {
            throw new \InvalidArgumentException('Deze klant heeft al een actief individueel schema voor hetzelfde factuuritem.');
        }
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `admin_invoice_recurring_member` m '
            . 'INNER JOIN `admin_invoice_recurring` p ON p.`tenant_id`=m.`tenant_id` '
            . 'AND p.`public_id`=m.`plan_public_id` WHERE m.`tenant_id`=:tenant '
            . 'AND m.`customer_public_id`=:customer AND m.`plan_public_id`<>:plan '
            . 'AND m.`active`=1 AND p.`active`=1 AND p.`catalog_item_public_id`=:item LIMIT 1'
        );
        $statement->execute([
            ':tenant' => $this->tenant->toString(), ':customer' => $customerPublicId,
            ':plan' => $plan['public_id'], ':item' => $plan['catalog_item_public_id'],
        ]);
        if ($statement->fetchColumn()) {
            throw new \InvalidArgumentException('Deze klant heeft al een ander actief schema voor hetzelfde factuuritem.');
        }
    }

    private function nextEnrollmentDate(array $plan, string $today): string
    {
        $date = (string)$plan['next_issue_date'];
        new CalendarDate($date);
        for ($i = 0; $date < $today && $i < 1200; $i++) {
            $date = $this->nextDate($date, (int)$plan['day_of_month'], (int)$plan['frequency_months']);
        }
        if ($date < $today) {
            throw new \LogicException('De schemadatum ligt te ver in het verleden. Kies een recentere datum.');
        }
        return $date;
    }

    private function nextDate(string $date, int $day, int $months): string
    {
        $first = (new \DateTimeImmutable($date))->modify('first day of this month')
            ->modify('+' . $months . ' months');
        $day = min($day, (int)$first->format('t'));
        return $first->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
    }
}
