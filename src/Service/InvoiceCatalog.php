<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceCustomerSelection;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

/** Herbruikbare factuurregels. Facturen krijgen altijd een eigen kopie van de gekozen gegevens. */
final class InvoiceCatalog
{
    /** @var \PDO */ private $connection;
    /** @var TenantId */ private $tenant;
    /** @var PublicIdGeneratorInterface */ private $ids;
    /** @var array */ private $choiceFunctions;
    /** @var callable|null */ private $deletionGuard;

    public function __construct(
        \PDO $connection, TenantId $tenant, PublicIdGeneratorInterface $ids,
        ?array $choiceFunctions = null, ?callable $deletionGuard = null
    )
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->ids = $ids;
        $this->choiceFunctions = $choiceFunctions !== null ? $choiceFunctions
            : (function_exists('invoiceChoiceFunctions') ? invoiceChoiceFunctions() : []);
        $this->deletionGuard = $deletionGuard !== null ? $deletionGuard
            : (function_exists('invoiceCatalogDeleteBlocker') ? 'invoiceCatalogDeleteBlocker' : null);
    }

    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM `admin_invoice_catalog_item` WHERE `tenant_id` = :tenant';
        if ($activeOnly) {
            $sql .= ' AND `active` = 1';
        }
        $sql .= ' ORDER BY `name` ASC, `public_id` ASC';
        $statement = $this->connection->prepare($sql);
        $statement->execute([':tenant' => $this->tenant->toString()]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(string $publicId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_catalog_item` WHERE `tenant_id` = :tenant '
            . 'AND `public_id` = :public_id LIMIT 1'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':public_id' => trim($publicId)]);
        $item = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($item) ? $item : null;
    }

    public function deletionBlockReason(string $publicId): ?string
    {
        $item = $this->find($publicId);
        if ($item === null) {
            throw new \InvalidArgumentException('Dit factuuritem bestaat niet.');
        }
        if ($this->children($publicId, false) !== []) {
            return 'Verwijder eerst de varianten van deze keuzegroep.';
        }
        $parentId = trim((string)($item['parent_public_id'] ?? ''));
        if ($parentId !== '' && $this->referenceBlockReason($parentId) !== null) {
            return 'Deze variant hoort bij een keuzegroep die nog aan een schema of factuur is gekoppeld.';
        }
        $reason = $this->referenceBlockReason($publicId);
        if ($reason !== null) {
            return $reason;
        }
        if ($this->deletionGuard !== null) {
            $reason = call_user_func($this->deletionGuard, $item);
            if (is_string($reason) && trim($reason) !== '') {
                return $reason;
            }
        }
        return null;
    }

    private function referenceBlockReason(string $publicId): ?string
    {
        $references = [
            ['admin_invoice_recurring', 'Dit item is gekoppeld aan een herhaalschema.'],
            ['admin_invoice_recurring_member_item', 'Dit item is gekoppeld aan een klant binnen een herhaalschema.'],
            ['admin_invoice_line', 'Dit item is gebruikt op een factuur.'],
        ];
        foreach ($references as $reference) {
            $statement = $this->connection->prepare(
                'SELECT 1 FROM `' . $reference[0] . '` WHERE `tenant_id`=:tenant '
                . 'AND `catalog_item_public_id`=:item LIMIT 1'
            );
            $statement->execute([
                ':tenant' => $this->tenant->toString(), ':item' => $publicId,
            ]);
            if ($statement->fetchColumn() !== false) {
                return $reference[1];
            }
        }
        return null;
    }

    public function delete(string $publicId): void
    {
        $publicId = trim($publicId);
        $this->connection->beginTransaction();
        try {
            $reason = $this->deletionBlockReason($publicId);
            if ($reason !== null) {
                throw new \InvalidArgumentException($reason . ' Pauzeer het item als je het niet meer wilt gebruiken.');
            }
            $statement = $this->connection->prepare(
                'DELETE FROM `admin_invoice_catalog_item` WHERE `tenant_id`=:tenant AND `public_id`=:item'
            );
            $statement->execute([':tenant' => $this->tenant->toString(), ':item' => $publicId]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('Het factuuritem kon niet worden verwijderd.');
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function allActiveLines(): array
    {
        return array_values(array_filter($this->all(true), function (array $item): bool {
            return !$this->isGroup($item);
        }));
    }

    public function allActiveAssignable(): array
    {
        return array_values(array_filter($this->all(true), static function (array $item): bool {
            return trim((string)($item['parent_public_id'] ?? '')) === '';
        }));
    }

    public function choiceFunctions(): array
    {
        $labels = [];
        foreach ($this->choiceFunctions as $key => $definition) {
            if (is_array($definition) && isset($definition['callback'])
                && is_callable($definition['callback'])) {
                $labels[$key] = [
                    'label' => (string)($definition['label'] ?? $key),
                    'variants' => (array)($definition['variants'] ?? []),
                ];
            }
        }
        return $labels;
    }

    public function isGroup(array $item): bool
    {
        return (string)($item['item_type'] ?? '') === 'group';
    }

    public function children(string $groupPublicId, bool $activeOnly = true): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_catalog_item` WHERE `tenant_id`=:tenant '
            . 'AND `parent_public_id`=:parent' . ($activeOnly ? ' AND `active`=1' : '')
            . ' ORDER BY `name`,`public_id`'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':parent' => $groupPublicId]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array|false A selected direct child and optional validated quantity, or no line. */
    public function choose(array $group, InvoiceCustomerSelection $customer, string $issueDate)
    {
        if (!$this->isGroup($group) || (int)$group['active'] !== 1) {
            throw new \InvalidArgumentException('Kies een actieve factuuritemgroep.');
        }
        $key = (string)($group['choose_function'] ?? '');
        $definition = $this->choiceFunctions[$key] ?? null;
        if (!is_array($definition) || !isset($definition['callback'])
            || !is_callable($definition['callback'])) {
            throw new \InvalidArgumentException('De keuzefunctie van deze itemgroep is niet beschikbaar.');
        }
        $children = $this->children((string)$group['public_id']);
        if ($children === []) {
            throw new \InvalidArgumentException('Deze itemgroep heeft geen actieve varianten.');
        }
        $chosen = call_user_func($definition['callback'], $customer, $group, $children, $issueDate);
        if ($chosen === false) {
            return false;
        }
        if (!is_array($chosen) || !isset($chosen['catalog_item_public_id'])) {
            throw new \UnexpectedValueException('De keuzefunctie moet een kinditem of false teruggeven.');
        }
        $chosenId = trim((string)$chosen['catalog_item_public_id']);
        foreach ($children as $child) {
            if ((string)$child['public_id'] === $chosenId) {
                $result = ['catalog_item_public_id' => $chosenId];
                if (isset($chosen['quantity'])) {
                    $quantity = Quantity::fromDecimal((string)$chosen['quantity']);
                    if ($quantity->getScaledValue() <= 0) {
                        throw new \InvalidArgumentException('Een automatisch aantal moet groter dan nul zijn.');
                    }
                    $result['quantity'] = (string)$chosen['quantity'];
                }
                return $result;
            }
        }
        throw new \UnexpectedValueException('De keuzefunctie gaf geen actief kind van deze groep terug.');
    }

    public function save(array $input): string
    {
        $name = trim((string)($input['name'] ?? ''));
        $itemType = trim((string)($input['item_type'] ?? 'item'));
        $parentId = trim((string)($input['parent_public_id'] ?? ''));
        $variantKey = trim((string)($input['variant_key'] ?? ''));
        $chooseFunction = trim((string)($input['choose_function'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $quantity = trim((string)($input['quantity'] ?? '1'));
        $unit = trim((string)($input['unit'] ?? 'stuk'));
        $unitPrice = trim((string)($input['unit_price'] ?? ''));
        $currency = strtoupper(trim((string)($input['currency'] ?? 'EUR')));
        $taxRate = trim((string)($input['tax_rate'] ?? ''));
        $taxCategory = trim((string)($input['tax_category'] ?? ''));
        $active = !empty($input['active']) ? 1 : 0;
        if ($name === '' || strlen($name) > 255 || !in_array($itemType, ['item', 'group'], true)) {
            throw new \InvalidArgumentException('Geef een naam en een geldig itemtype op.');
        }
        $publicId = trim((string)($input['public_id'] ?? ''));
        $existing = $publicId !== '' ? $this->find($publicId) : null;
        if ($publicId !== '' && $existing === null) {
            throw new \InvalidArgumentException('Dit catalogusitem bestaat niet.');
        }
        if ($existing !== null && $this->isGroup($existing) !== ($itemType === 'group')) {
            throw new \InvalidArgumentException('Het type van een bestaand item kun je niet wijzigen.');
        }
        if ($itemType === 'group') {
            if ($parentId !== '' || !isset($this->choiceFunctions[$chooseFunction])
                || !is_callable($this->choiceFunctions[$chooseFunction]['callback'] ?? null)) {
                throw new \InvalidArgumentException('Kies een geregistreerde keuzefunctie voor de groep.');
            }
            $description = $name;
            $quantity = '1';
            $unit = 'groep';
            $unitPrice = '0';
            $taxRate = '0';
            $taxCategory = 'standard';
            $variantKey = '';
        } else {
            $chooseFunction = '';
            if ($description === '' || strlen($description) > 500) {
                throw new \InvalidArgumentException('Geef een factuuromschrijving op.');
            }
            if ($parentId !== '') {
                $parent = $this->find($parentId);
                if ($parent === null || !$this->isGroup($parent)) {
                    throw new \InvalidArgumentException('Kies een bestaande itemgroep als bovenliggend item.');
                }
                $variants = (array)($this->choiceFunctions[$parent['choose_function']]['variants'] ?? []);
                if ($variants !== [] && !array_key_exists($variantKey, $variants)) {
                    throw new \InvalidArgumentException('Kies een geldige variant voor deze itemgroep.');
                }
                foreach ($this->children($parentId, false) as $sibling) {
                    if ((string)$sibling['variant_key'] === $variantKey
                        && (string)$sibling['public_id'] !== $publicId) {
                        throw new \InvalidArgumentException('Deze variant is al aan de groep gekoppeld.');
                    }
                }
            } else {
                $variantKey = '';
            }
        }
        if (strlen($variantKey) > 64 || strlen($chooseFunction) > 128) {
            throw new \InvalidArgumentException('De functie- of variantsleutel is te lang.');
        }
        if ($description === '' || strlen($description) > 500) {
            throw new \InvalidArgumentException('Geef een naam en een factuuromschrijving op.');
        }
        $currencyObject = new Currency($currency);
        if ($itemType === 'group' && $existing !== null
            && $currency !== (string)$existing['currency']
            && $this->children($publicId, false) !== []) {
            throw new \InvalidArgumentException('Wijzig eerst de valuta van alle varianten voordat je de groepsvaluta wijzigt.');
        }
        if ($parentId !== '' && isset($parent) && $currency !== (string)$parent['currency']) {
            throw new \InvalidArgumentException('Een variant moet dezelfde valuta als de groep hebben.');
        }
        $price = Money::fromDecimal($unitPrice, $currencyObject);
        if ($price->getMinorUnits() < 0) {
            throw new \InvalidArgumentException('De standaardprijs mag niet negatief zijn.');
        }
        new InvoiceLine(
            $this->ids->generate(), $description, Quantity::fromDecimal($quantity), $unit,
            $price, TaxRate::fromPercentage($taxRate), new TaxCategory($taxCategory), 10
        );
        $now = time();
        if ($publicId === '') {
            $publicId = $this->ids->generate();
            $statement = $this->connection->prepare(
                'INSERT INTO `admin_invoice_catalog_item` '
                . '(`tenant_id`,`public_id`,`name`,`item_type`,`parent_public_id`,`variant_key`, '
                . '`choose_function`,`description`,`quantity`,`unit`,`unit_price`,`currency`, '
                . '`tax_rate`,`tax_category`,`active`,`created_at`,`updated_at`) VALUES '
                . '(:tenant,:public_id,:name,:item_type,:parent,:variant,:choose_function, '
                . ':description,:quantity,:unit,:unit_price,:currency,:tax_rate,:tax_category, '
                . ':active,:created_at,:updated_at)'
            );
            $statement->execute([
                ':tenant' => $this->tenant->toString(), ':public_id' => $publicId,
                ':name' => $name, ':item_type' => $itemType, ':parent' => $parentId,
                ':variant' => $variantKey, ':choose_function' => $chooseFunction,
                ':description' => $description, ':quantity' => $quantity,
                ':unit' => $unit, ':unit_price' => $unitPrice, ':currency' => $currency,
                ':tax_rate' => $taxRate, ':tax_category' => $taxCategory,
                ':active' => $active, ':created_at' => $now, ':updated_at' => $now,
            ]);
            return $publicId;
        }
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_catalog_item` SET `name`=:name,`item_type`=:item_type, '
            . '`parent_public_id`=:parent,`variant_key`=:variant,`choose_function`=:choose_function, '
            . '`description`=:description, '
            . '`quantity`=:quantity,`unit`=:unit,`unit_price`=:unit_price,`currency`=:currency, '
            . '`tax_rate`=:tax_rate,`tax_category`=:tax_category,`active`=:active,`updated_at`=:updated_at '
            . 'WHERE `tenant_id`=:tenant AND `public_id`=:public_id'
        );
        $statement->execute([
            ':name' => $name, ':item_type' => $itemType, ':parent' => $parentId,
            ':variant' => $variantKey, ':choose_function' => $chooseFunction,
            ':description' => $description, ':quantity' => $quantity,
            ':unit' => $unit, ':unit_price' => $unitPrice, ':currency' => $currency,
            ':tax_rate' => $taxRate, ':tax_category' => $taxCategory, ':active' => $active,
            ':updated_at' => $now, ':tenant' => $this->tenant->toString(), ':public_id' => $publicId,
        ]);
        return $publicId;
    }

    public function line(string $publicId, string $currency): array
    {
        $item = $this->find($publicId);
        if ($item === null || (int)$item['active'] !== 1 || $this->isGroup($item)) {
            throw new \InvalidArgumentException('Kies een actief catalogusitem.');
        }
        if ($item['currency'] !== $currency) {
            throw new \InvalidArgumentException('De valuta van het catalogusitem wijkt af van de factuur.');
        }
        return [
            'description' => (string)$item['description'],
            'quantity' => (string)$item['quantity'],
            'unit' => (string)$item['unit'],
            'unit_price' => (string)$item['unit_price'],
            'tax_rate' => (string)$item['tax_rate'],
            'tax_category' => (string)$item['tax_category'],
        ];
    }
}
