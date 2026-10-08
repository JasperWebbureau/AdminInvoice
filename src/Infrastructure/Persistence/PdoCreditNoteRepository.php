<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Contract\CreditNoteRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxSummary;

final class PdoCreditNoteRepository implements CreditNoteRepositoryInterface
{
    private const CREDIT_TABLE = 'admin_credit_note';
    private const LINE_TABLE = 'admin_credit_note_line';
    private const INVOICE_TABLE = 'admin_invoice';
    private const TAX_SNAPSHOT_VERSION = 1;

    /** @var \PDO */ private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function insert(CreditNote $creditNote, int $createdAt): void
    {
        $this->assertTransaction();
        if ($createdAt < 0) {
            throw new \InvalidArgumentException('Aanmaaktijdstip van creditnota is ongeldig.');
        }
        $totals = $creditNote->getTotals();
        $statement = $this->connection->prepare(
            'INSERT INTO `' . self::CREDIT_TABLE . '` ('
            . '`tenant_id`, `public_id`, `credit_note_number`, `sequence_value`, '
            . '`original_invoice_public_id`, `original_invoice_number`, `currency`, '
            . '`customer_snapshot`, `billing_address_snapshot`, `issue_date`, `reason`, `finalized_at`, '
            . '`net_total_minor`, `tax_total_minor`, `gross_total_minor`, `tax_summary_snapshot`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :credit_note_number, :sequence_value, '
            . ':original_invoice_public_id, :original_invoice_number, :currency, '
            . ':customer_snapshot, :billing_address_snapshot, :issue_date, :reason, :finalized_at, '
            . ':net_total_minor, :tax_total_minor, :gross_total_minor, :tax_summary_snapshot, :created_at, :updated_at'
            . ')'
        );
        $statement->execute([
            ':tenant_id' => $creditNote->getTenantId()->toString(),
            ':public_id' => $creditNote->getPublicId(),
            ':credit_note_number' => $creditNote->getNumber(),
            ':sequence_value' => $creditNote->getSequenceValue(),
            ':original_invoice_public_id' => $creditNote->getOriginalInvoicePublicId(),
            ':original_invoice_number' => $creditNote->getOriginalInvoiceNumber(),
            ':currency' => $creditNote->getCurrency()->getCode(),
            ':customer_snapshot' => $this->encodeJson($creditNote->getCustomerSnapshot()->toArray()),
            ':billing_address_snapshot' => $this->encodeJson($creditNote->getBillingAddressSnapshot()->toArray()),
            ':issue_date' => $creditNote->getIssueDate()->getValue(),
            ':reason' => $creditNote->getReason(),
            ':finalized_at' => $creditNote->getFinalizedAt(),
            ':net_total_minor' => $totals->getNet()->getMinorUnits(),
            ':tax_total_minor' => $totals->getTax()->getMinorUnits(),
            ':gross_total_minor' => $totals->getGross()->getMinorUnits(),
            ':tax_summary_snapshot' => $this->encodeTaxSnapshot($totals),
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt,
        ]);

        $creditNoteId = (int)$this->connection->lastInsertId();
        if ($creditNoteId < 1) {
            throw new \RuntimeException('Database gaf geen geldige interne creditnota-id terug.');
        }
        $lineStatement = $this->connection->prepare(
            'INSERT INTO `' . self::LINE_TABLE . '` ('
            . '`tenant_id`, `public_id`, `credit_note_id`, `description`, `quantity_scaled`, `unit`, '
            . '`unit_price_minor`, `tax_rate_basis_points`, `tax_category`, `position`, '
            . '`net_total_minor`, `tax_total_minor`, `gross_total_minor`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :credit_note_id, :description, :quantity_scaled, :unit, '
            . ':unit_price_minor, :tax_rate_basis_points, :tax_category, :position, '
            . ':net_total_minor, :tax_total_minor, :gross_total_minor, :created_at, :updated_at'
            . ')'
        );
        foreach ($creditNote->getLines() as $line) {
            $lineStatement->execute([
                ':tenant_id' => $creditNote->getTenantId()->toString(),
                ':public_id' => $line->getPublicId(),
                ':credit_note_id' => $creditNoteId,
                ':description' => $line->getDescription(),
                ':quantity_scaled' => $line->getQuantity()->getScaledValue(),
                ':unit' => $line->getUnit(),
                ':unit_price_minor' => $line->getUnitPrice()->getMinorUnits(),
                ':tax_rate_basis_points' => $line->getTaxRate()->getBasisPoints(),
                ':tax_category' => $line->getTaxCategory()->getValue(),
                ':position' => $line->getPosition(),
                ':net_total_minor' => $line->calculateNet()->getMinorUnits(),
                ':tax_total_minor' => $line->calculateTax()->getMinorUnits(),
                ':gross_total_minor' => $line->calculateGross()->getMinorUnits(),
                ':created_at' => $createdAt,
                ':updated_at' => $createdAt,
            ]);
        }
    }

    public function markOriginalInvoiceCredited(Invoice $invoice, int $updatedAt): void
    {
        $this->assertTransaction();
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::CREDITED || $updatedAt < 0) {
            throw new \DomainException('Alleen een gecrediteerde factuur kan worden opgeslagen.');
        }
        $statement = $this->connection->prepare(
            'UPDATE `' . self::INVOICE_TABLE . '` SET `status` = :credited, `updated_at` = :updated_at '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id AND `status` = :finalized'
        );
        $statement->execute([
            ':credited' => InvoiceStatus::CREDITED,
            ':updated_at' => $updatedAt,
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
            ':finalized' => InvoiceStatus::FINALIZED,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Bronfactuur is niet langer beschikbaar voor volledige creditering.');
        }
    }

    public function findByPublicId(TenantId $tenantId, string $publicId): ?CreditNote
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            throw new \InvalidArgumentException('Publieke creditnota-id is verplicht.');
        }
        $statement = $this->connection->prepare(
            'SELECT * FROM `' . self::CREDIT_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id LIMIT 1'
        );
        $statement->execute([':tenant_id' => $tenantId->toString(), ':public_id' => $publicId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    private function hydrate(array $row): CreditNote
    {
        $tenantId = new TenantId((string)$row['tenant_id']);
        $currency = new Currency((string)$row['currency']);
        $statement = $this->connection->prepare(
            'SELECT * FROM `' . self::LINE_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `credit_note_id` = :credit_note_id ORDER BY `position`, `id`'
        );
        $statement->execute([':tenant_id' => $tenantId->toString(), ':credit_note_id' => (int)$row['id']]);
        $lines = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $line) {
            $lines[] = new InvoiceLine(
                (string)$line['public_id'],
                (string)$line['description'],
                Quantity::fromScaled((int)$line['quantity_scaled']),
                (string)$line['unit'],
                new Money((int)$line['unit_price_minor'], $currency),
                new TaxRate((int)$line['tax_rate_basis_points']),
                new TaxCategory((string)$line['tax_category']),
                (int)$line['position']
            );
        }
        $totals = $this->decodeTotals($row, $currency);

        return new CreditNote(
            (string)$row['public_id'],
            $tenantId,
            (string)$row['credit_note_number'],
            (int)$row['sequence_value'],
            (string)$row['original_invoice_public_id'],
            (string)$row['original_invoice_number'],
            $currency,
            PartySnapshot::fromArray($this->decodeJson((string)$row['customer_snapshot'])),
            AddressSnapshot::fromArray($this->decodeJson((string)$row['billing_address_snapshot'])),
            new CalendarDate((string)$row['issue_date']),
            (string)$row['reason'],
            $lines,
            $totals,
            (int)$row['finalized_at']
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
        return $this->encodeJson(['schema_version' => self::TAX_SNAPSHOT_VERSION, 'groups' => $groups]);
    }

    private function decodeTotals(array $row, Currency $currency): InvoiceTotals
    {
        $snapshot = $this->decodeJson((string)$row['tax_summary_snapshot']);
        if ((int)($snapshot['schema_version'] ?? 0) !== self::TAX_SNAPSHOT_VERSION || !is_array($snapshot['groups'] ?? null)) {
            throw new \UnexpectedValueException('Ongeldige btw-snapshot in creditnota.');
        }
        $summaries = [];
        foreach ($snapshot['groups'] as $group) {
            if (!is_array($group)) {
                throw new \UnexpectedValueException('Ongeldige btw-groep in creditnota.');
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

    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \UnexpectedValueException('Creditnotasnapshot kon niet worden opgeslagen.');
        }
        return $json;
    }

    private function decodeJson(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \UnexpectedValueException('Creditnotasnapshot bevat ongeldige JSON.');
        }
        return $data;
    }

    private function assertTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Creditnotabewerking vereist een actieve transactie.');
        }
    }
}
