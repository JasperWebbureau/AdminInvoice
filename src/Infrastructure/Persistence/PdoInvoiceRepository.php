<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxSummary;
use Flexgrid\Modules\AdminInvoice\Exception\DuplicateExternalReferenceException;

final class PdoInvoiceRepository implements InvoiceRepositoryInterface
{
    private const INVOICE_TABLE = 'admin_invoice';
    private const LINE_TABLE = 'admin_invoice_line';
    private const TAX_SNAPSHOT_VERSION = 1;

    /** @var \PDO */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function insert(Invoice $invoice, InvoiceTotals $totals, int $createdAt): void
    {
        if ($createdAt < 0) {
            throw new \InvalidArgumentException('Aanmaaktijdstip is ongeldig.');
        }

        $source = $invoice->getSource() === '' ? null : $invoice->getSource();
        $externalId = $invoice->getExternalId() === '' ? null : $invoice->getExternalId();
        $statement = $this->connection->prepare(
            'INSERT INTO `' . self::INVOICE_TABLE . '` ('
            . '`tenant_id`, `public_id`, `invoice_number`, `invoice_sequence_value`, `status`, `payment_status`, `currency`, '
            . '`customer_reference`, `customer_snapshot`, `billing_address_snapshot`, `issue_date`, `due_date`, '
            . '`finalized_at`, `notes`, `source`, `external_id`, `net_total_minor`, `tax_total_minor`, '
            . '`gross_total_minor`, `tax_summary_snapshot`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :invoice_number, :invoice_sequence_value, :status, :payment_status, :currency, '
            . ':customer_reference, :customer_snapshot, :billing_address_snapshot, :issue_date, :due_date, '
            . ':finalized_at, :notes, :source, :external_id, :net_total_minor, :tax_total_minor, '
            . ':gross_total_minor, :tax_summary_snapshot, :created_at, :updated_at'
            . ')'
        );

        try {
            $statement->execute([
                ':tenant_id' => $invoice->getTenantId()->toString(),
                ':public_id' => $invoice->getPublicId(),
                ':invoice_number' => $invoice->getInvoiceNumber(),
                ':invoice_sequence_value' => $invoice->getInvoiceSequenceValue(),
                ':status' => $invoice->getStatus()->getValue(),
                ':payment_status' => $invoice->getPaymentStatus()->getValue(),
                ':currency' => $invoice->getCurrency()->getCode(),
                ':customer_reference' => $this->nullable($invoice->getCustomerReference()),
                ':customer_snapshot' => $this->encodeJson($invoice->getCustomerSnapshot()->toArray()),
                ':billing_address_snapshot' => $this->encodeJson($invoice->getBillingAddressSnapshot()->toArray()),
                ':issue_date' => $invoice->getIssueDate()->getValue(),
                ':due_date' => $invoice->getDueDate()->getValue(),
                ':finalized_at' => $invoice->getFinalizedAt(),
                ':notes' => $this->nullable($invoice->getNotes()),
                ':source' => $source,
                ':external_id' => $externalId,
                ':net_total_minor' => $totals->getNet()->getMinorUnits(),
                ':tax_total_minor' => $totals->getTax()->getMinorUnits(),
                ':gross_total_minor' => $totals->getGross()->getMinorUnits(),
                ':tax_summary_snapshot' => $this->encodeTaxSnapshot($totals),
                ':created_at' => $createdAt,
                ':updated_at' => $createdAt,
            ]);
        } catch (\PDOException $exception) {
            if ($source !== null && $this->isIntegrityViolation($exception)) {
                throw new DuplicateExternalReferenceException(
                    'Externe factuurreferentie bestaat al voor deze tenant.',
                    0,
                    $exception
                );
            }
            throw $exception;
        }

        $invoiceId = (int)$this->connection->lastInsertId();
        if ($invoiceId < 1) {
            throw new \RuntimeException('Database gaf geen geldige interne factuur-id terug.');
        }

        $lineStatement = $this->connection->prepare(
            'INSERT INTO `' . self::LINE_TABLE . '` ('
            . '`tenant_id`, `public_id`, `invoice_id`, `description`, `quantity_scaled`, `unit`, '
            . '`unit_price_minor`, `tax_rate_basis_points`, `tax_category`, `catalog_item_public_id`, `position`, '
            . '`net_total_minor`, `tax_total_minor`, `gross_total_minor`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :invoice_id, :description, :quantity_scaled, :unit, '
            . ':unit_price_minor, :tax_rate_basis_points, :tax_category, :catalog_item_public_id, :position, '
            . ':net_total_minor, :tax_total_minor, :gross_total_minor, :created_at, :updated_at'
            . ')'
        );
        foreach ($invoice->getLines() as $line) {
            $lineStatement->execute([
                ':tenant_id' => $invoice->getTenantId()->toString(),
                ':public_id' => $line->getPublicId(),
                ':invoice_id' => $invoiceId,
                ':description' => $line->getDescription(),
                ':quantity_scaled' => $line->getQuantity()->getScaledValue(),
                ':unit' => $line->getUnit(),
                ':unit_price_minor' => $line->getUnitPrice()->getMinorUnits(),
                ':tax_rate_basis_points' => $line->getTaxRate()->getBasisPoints(),
                ':tax_category' => $line->getTaxCategory()->getValue(),
                ':catalog_item_public_id' => $this->nullable($line->getCatalogItemPublicId()),
                ':position' => $line->getPosition(),
                ':net_total_minor' => $line->calculateNet()->getMinorUnits(),
                ':tax_total_minor' => $line->calculateTax()->getMinorUnits(),
                ':gross_total_minor' => $line->calculateGross()->getMinorUnits(),
                ':created_at' => $createdAt,
                ':updated_at' => $createdAt,
            ]);
        }
    }

    public function findByPublicId(TenantId $tenantId, string $publicId): ?Invoice
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            throw new \InvalidArgumentException('Publieke factuur-id is verplicht.');
        }

        return $this->findOne(
            '`tenant_id` = :tenant_id AND `public_id` = :public_id',
            [':tenant_id' => $tenantId->toString(), ':public_id' => $publicId]
        );
    }

    public function findByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?Invoice
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Een factuurvergrendeling vereist een actieve transactie.');
        }
        $publicId = trim($publicId);
        if ($publicId === '') {
            throw new \InvalidArgumentException('Publieke factuur-id is verplicht.');
        }

        return $this->findOne(
            '`tenant_id` = :tenant_id AND `public_id` = :public_id',
            [':tenant_id' => $tenantId->toString(), ':public_id' => $publicId],
            true
        );
    }

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Invoice
    {
        $source = trim($source);
        $externalId = trim($externalId);
        if ($source === '' || $externalId === '') {
            throw new \InvalidArgumentException('Bron en externe id zijn verplicht voor deze lookup.');
        }

        return $this->findOne(
            '`tenant_id` = :tenant_id AND `source` = :source AND `external_id` = :external_id',
            [':tenant_id' => $tenantId->toString(), ':source' => $source, ':external_id' => $externalId]
        );
    }

    public function updateDraft(Invoice $invoice, InvoiceTotals $totals, int $updatedAt): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Een conceptfactuurupdate vereist een actieve transactie.');
        }
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Alleen een conceptfactuur mag worden opgeslagen.');
        }
        if ($updatedAt < 0) {
            throw new \InvalidArgumentException('Wijzigingstijdstip is ongeldig.');
        }

        $identityStatement = $this->connection->prepare(
            'SELECT `id`, `status` FROM `' . self::INVOICE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id LIMIT 1 FOR UPDATE'
        );
        $identityStatement->execute([
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
        ]);
        $identity = $identityStatement->fetch(\PDO::FETCH_ASSOC);
        if ($identity === false) {
            throw new \RuntimeException('Conceptfactuur bestaat niet meer.');
        }
        if ((string)$identity['status'] !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Factuur is niet langer een concept.');
        }
        $invoiceId = (int)$identity['id'];

        $createdAtByPublicId = [];
        $createdStatement = $this->connection->prepare(
            'SELECT `public_id`, `created_at` FROM `' . self::LINE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `invoice_id` = :invoice_id'
        );
        $createdStatement->execute([
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':invoice_id' => $invoiceId,
        ]);
        foreach ($createdStatement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $createdAtByPublicId[(string)$row['public_id']] = (int)$row['created_at'];
        }

        $statement = $this->connection->prepare(
            'UPDATE `' . self::INVOICE_TABLE . '` SET '
            . '`customer_reference` = :customer_reference, `customer_snapshot` = :customer_snapshot, '
            . '`billing_address_snapshot` = :billing_address_snapshot, `issue_date` = :issue_date, '
            . '`due_date` = :due_date, `notes` = :notes, `net_total_minor` = :net_total_minor, '
            . '`tax_total_minor` = :tax_total_minor, `gross_total_minor` = :gross_total_minor, '
            . '`tax_summary_snapshot` = :tax_summary_snapshot, `updated_at` = :updated_at '
            . 'WHERE `id` = :id AND `tenant_id` = :tenant_id AND `status` = :draft_status'
        );
        $statement->execute([
            ':customer_reference' => $this->nullable($invoice->getCustomerReference()),
            ':customer_snapshot' => $this->encodeJson($invoice->getCustomerSnapshot()->toArray()),
            ':billing_address_snapshot' => $this->encodeJson($invoice->getBillingAddressSnapshot()->toArray()),
            ':issue_date' => $invoice->getIssueDate()->getValue(),
            ':due_date' => $invoice->getDueDate()->getValue(),
            ':notes' => $this->nullable($invoice->getNotes()),
            ':net_total_minor' => $totals->getNet()->getMinorUnits(),
            ':tax_total_minor' => $totals->getTax()->getMinorUnits(),
            ':gross_total_minor' => $totals->getGross()->getMinorUnits(),
            ':tax_summary_snapshot' => $this->encodeTaxSnapshot($totals),
            ':updated_at' => $updatedAt,
            ':id' => $invoiceId,
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':draft_status' => InvoiceStatus::DRAFT,
        ]);

        $deleteStatement = $this->connection->prepare(
            'DELETE FROM `' . self::LINE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `invoice_id` = :invoice_id'
        );
        $deleteStatement->execute([
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':invoice_id' => $invoiceId,
        ]);

        $lineStatement = $this->connection->prepare(
            'INSERT INTO `' . self::LINE_TABLE . '` ('
            . '`tenant_id`, `public_id`, `invoice_id`, `description`, `quantity_scaled`, `unit`, '
            . '`unit_price_minor`, `tax_rate_basis_points`, `tax_category`, `catalog_item_public_id`, `position`, '
            . '`net_total_minor`, `tax_total_minor`, `gross_total_minor`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :invoice_id, :description, :quantity_scaled, :unit, '
            . ':unit_price_minor, :tax_rate_basis_points, :tax_category, :catalog_item_public_id, :position, '
            . ':net_total_minor, :tax_total_minor, :gross_total_minor, :created_at, :updated_at'
            . ')'
        );
        foreach ($invoice->getLines() as $line) {
            $lineStatement->execute([
                ':tenant_id' => $invoice->getTenantId()->toString(),
                ':public_id' => $line->getPublicId(),
                ':invoice_id' => $invoiceId,
                ':description' => $line->getDescription(),
                ':quantity_scaled' => $line->getQuantity()->getScaledValue(),
                ':unit' => $line->getUnit(),
                ':unit_price_minor' => $line->getUnitPrice()->getMinorUnits(),
                ':tax_rate_basis_points' => $line->getTaxRate()->getBasisPoints(),
                ':tax_category' => $line->getTaxCategory()->getValue(),
                ':catalog_item_public_id' => $this->nullable($line->getCatalogItemPublicId()),
                ':position' => $line->getPosition(),
                ':net_total_minor' => $line->calculateNet()->getMinorUnits(),
                ':tax_total_minor' => $line->calculateTax()->getMinorUnits(),
                ':gross_total_minor' => $line->calculateGross()->getMinorUnits(),
                ':created_at' => $createdAtByPublicId[$line->getPublicId()] ?? $updatedAt,
                ':updated_at' => $updatedAt,
            ]);
        }
    }

    public function correctIssueDate(Invoice $invoice, int $updatedAt): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Een factuurdatumcorrectie vereist een actieve transactie.');
        }
        if (!in_array($invoice->getStatus()->getValue(), [InvoiceStatus::DRAFT, InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)) {
            throw new \DomainException('De factuurdatum van deze factuur kan niet worden gewijzigd.');
        }

        $statement = $this->connection->prepare(
            'UPDATE `' . self::INVOICE_TABLE . '` SET `issue_date` = :issue_date, `updated_at` = :updated_at '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id AND `status` = :status'
        );
        $statement->execute([
            ':issue_date' => $invoice->getIssueDate()->getValue(),
            ':updated_at' => $updatedAt,
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
            ':status' => $invoice->getStatus()->getValue(),
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Factuur is gewijzigd; laad de pagina opnieuw.');
        }
    }

    public function deleteDraft(Invoice $invoice): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Een conceptfactuur verwijderen vereist een actieve transactie.');
        }
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Alleen een conceptfactuur mag worden verwijderd.');
        }

        $identityStatement = $this->connection->prepare(
            'SELECT `id`, `status` FROM `' . self::INVOICE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id LIMIT 1 FOR UPDATE'
        );
        $identityStatement->execute([
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
        ]);
        $identity = $identityStatement->fetch(\PDO::FETCH_ASSOC);
        if ($identity === false) {
            throw new \DomainException('Conceptfactuur bestaat niet meer.');
        }
        if ((string)$identity['status'] !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Factuur is niet langer een concept.');
        }

        $parameters = [
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':invoice_id' => (int)$identity['id'],
        ];
        $deleteLines = $this->connection->prepare(
            'DELETE FROM `' . self::LINE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `invoice_id` = :invoice_id'
        );
        $deleteLines->execute($parameters);

        $deleteInvoice = $this->connection->prepare(
            'DELETE FROM `' . self::INVOICE_TABLE . '` '
            . 'WHERE `id` = :invoice_id AND `tenant_id` = :tenant_id AND `status` = :draft_status'
        );
        $deleteInvoice->execute([
            ':invoice_id' => (int)$identity['id'],
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':draft_status' => InvoiceStatus::DRAFT,
        ]);
        if ($deleteInvoice->rowCount() !== 1) {
            throw new \DomainException('Factuur is niet langer een concept en kon niet worden verwijderd.');
        }
    }

    public function finalize(Invoice $invoice): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Factuurfinalisatie vereist een actieve transactie.');
        }
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED
            || $invoice->getInvoiceNumber() === null
            || $invoice->getInvoiceSequenceValue() === null
            || $invoice->getFinalizedAt() === null
            || $invoice->getFinalizedTotals() === null
        ) {
            throw new \DomainException('Alleen een volledig gefinaliseerde factuur mag definitief worden opgeslagen.');
        }

        $totals = $invoice->getFinalizedTotals();
        $statement = $this->connection->prepare(
            'UPDATE `' . self::INVOICE_TABLE . '` SET '
            . '`invoice_number` = :invoice_number, `invoice_sequence_value` = :invoice_sequence_value, '
            . '`status` = :final_status, `finalized_at` = :finalized_at, '
            . '`net_total_minor` = :net_total_minor, `tax_total_minor` = :tax_total_minor, '
            . '`gross_total_minor` = :gross_total_minor, `tax_summary_snapshot` = :tax_summary_snapshot, '
            . '`updated_at` = :updated_at '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id AND `status` = :draft_status'
        );
        $statement->execute([
            ':invoice_number' => $invoice->getInvoiceNumber(),
            ':invoice_sequence_value' => $invoice->getInvoiceSequenceValue(),
            ':final_status' => InvoiceStatus::FINALIZED,
            ':finalized_at' => $invoice->getFinalizedAt(),
            ':net_total_minor' => $totals->getNet()->getMinorUnits(),
            ':tax_total_minor' => $totals->getTax()->getMinorUnits(),
            ':gross_total_minor' => $totals->getGross()->getMinorUnits(),
            ':tax_summary_snapshot' => $this->encodeTaxSnapshot($totals),
            ':updated_at' => $invoice->getFinalizedAt(),
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
            ':draft_status' => InvoiceStatus::DRAFT,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Factuur is niet langer een concept en kon niet worden gefinaliseerd.');
        }
    }

    private function findOne(string $where, array $parameters, bool $forUpdate = false): ?Invoice
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `' . self::INVOICE_TABLE . '` WHERE ' . $where . ' LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute($parameters);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateInvoice($row);
    }

    private function hydrateInvoice(array $row): Invoice
    {
        $tenantId = new TenantId((string)$row['tenant_id']);
        $currency = new Currency((string)$row['currency']);
        $lineStatement = $this->connection->prepare(
            'SELECT * FROM `' . self::LINE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `invoice_id` = :invoice_id '
            . 'ORDER BY `position`, `id`'
        );
        $lineStatement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':invoice_id' => (int)$row['id'],
        ]);

        $lines = [];
        foreach ($lineStatement->fetchAll(\PDO::FETCH_ASSOC) as $line) {
            $lines[] = new InvoiceLine(
                (string)$line['public_id'],
                (string)$line['description'],
                Quantity::fromScaled((int)$line['quantity_scaled']),
                (string)$line['unit'],
                new Money((int)$line['unit_price_minor'], $currency),
                new TaxRate((int)$line['tax_rate_basis_points']),
                new TaxCategory((string)$line['tax_category']),
                (int)$line['position'],
                (string)($line['catalog_item_public_id'] ?? '')
            );
        }

        $status = new InvoiceStatus((string)$row['status']);
        $finalizedTotals = null;
        if ($status->getValue() === InvoiceStatus::FINALIZED || $status->getValue() === InvoiceStatus::CREDITED) {
            $finalizedTotals = $this->decodeTotals($row, $currency);
        }

        return Invoice::reconstitute(
            (string)$row['public_id'],
            $tenantId,
            $currency,
            PartySnapshot::fromArray($this->decodeJson((string)$row['customer_snapshot'])),
            AddressSnapshot::fromArray($this->decodeJson((string)$row['billing_address_snapshot'])),
            new CalendarDate((string)$row['issue_date']),
            new CalendarDate((string)$row['due_date']),
            $status,
            new PaymentStatus((string)$row['payment_status']),
            $lines,
            (string)($row['customer_reference'] ?? ''),
            (string)($row['notes'] ?? ''),
            (string)($row['source'] ?? ''),
            (string)($row['external_id'] ?? ''),
            $row['invoice_number'] === null ? null : (string)$row['invoice_number'],
            $row['invoice_sequence_value'] === null ? null : (int)$row['invoice_sequence_value'],
            $row['finalized_at'] === null ? null : (int)$row['finalized_at'],
            $finalizedTotals
        );
    }

    private function decodeTotals(array $row, Currency $currency): InvoiceTotals
    {
        $snapshot = $this->decodeJson((string)$row['tax_summary_snapshot']);
        if ((int)($snapshot['schema_version'] ?? 0) !== self::TAX_SNAPSHOT_VERSION
            || !isset($snapshot['groups'])
            || !is_array($snapshot['groups'])
        ) {
            throw new \UnexpectedValueException('Ongeldige btw-snapshot in opgeslagen factuur.');
        }

        $summaries = [];
        foreach ($snapshot['groups'] as $group) {
            if (!is_array($group)) {
                throw new \UnexpectedValueException('Ongeldige btw-groep in opgeslagen factuur.');
            }
            $summaries[] = new TaxSummary(
                new TaxCategory((string)($group['category'] ?? '')),
                new TaxRate((int)($group['rate_basis_points'] ?? -1)),
                new Money((int)($group['net_minor'] ?? 0), $currency),
                new Money((int)($group['tax_minor'] ?? 0), $currency)
            );
        }

        return new InvoiceTotals(
            new Money((int)$row['net_total_minor'], $currency),
            new Money((int)$row['tax_total_minor'], $currency),
            new Money((int)$row['gross_total_minor'], $currency),
            $summaries
        );
    }

    private function encodeTaxSnapshot(InvoiceTotals $totals): string
    {
        $groups = [];
        foreach ($totals->getTaxSummaries() as $summary) {
            $groups[] = [
                'category' => $summary->getCategory()->getValue(),
                'rate_basis_points' => $summary->getRate()->getBasisPoints(),
                'net_minor' => $summary->getNet()->getMinorUnits(),
                'tax_minor' => $summary->getTax()->getMinorUnits(),
            ];
        }

        return $this->encodeJson([
            'schema_version' => self::TAX_SNAPSHOT_VERSION,
            'groups' => $groups,
        ]);
    }

    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \UnexpectedValueException('Snapshot kon niet als JSON worden opgeslagen.');
        }

        return $json;
    }

    private function decodeJson(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \UnexpectedValueException('Opgeslagen snapshot bevat ongeldige JSON.');
        }

        return $data;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function isIntegrityViolation(\PDOException $exception): bool
    {
        return (string)$exception->getCode() === '23000'
            || (isset($exception->errorInfo[0]) && (string)$exception->errorInfo[0] === '23000');
    }
}
