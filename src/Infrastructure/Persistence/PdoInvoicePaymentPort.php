<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\PayableInvoice;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoicePaymentPortInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoicePaymentDetailPortInterface;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

final class PdoInvoicePaymentPort implements InvoicePaymentPortInterface, InvoicePaymentDetailPortInterface
{
    private const TABLE = 'admin_invoice';

    /** @var \PDO */ private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function findPayableByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?PayableInvoice
    {
        $this->assertTransaction();
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }
        $statement = $this->connection->prepare(
            'SELECT `public_id`, `invoice_number`, `status`, `currency`, `gross_total_minor` '
            . 'FROM `' . self::TABLE . '` WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id FOR UPDATE'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':public_id' => $publicId,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new PayableInvoice(
            $tenantId,
            (string)$row['public_id'],
            (string)($row['invoice_number'] ?? ''),
            (string)$row['status'],
            new Money((int)$row['gross_total_minor'], new Currency((string)$row['currency']))
        );
    }

    public function synchronizePaymentStatus(
        PayableInvoice $invoice,
        Money $allocatedTotal,
        int $updatedAt
    ): string {
        $this->assertTransaction();
        if ($invoice->getStatus() !== InvoiceStatus::FINALIZED) {
            throw new \DomainException('Alleen een definitieve, niet-gecrediteerde factuur kan betalingen ontvangen.');
        }
        if (!$allocatedTotal->getCurrency()->equals($invoice->getGrossTotal()->getCurrency())) {
            throw new \InvalidArgumentException('Afgeletterd totaal gebruikt een andere valuta dan de factuur.');
        }
        $gross = $invoice->getGrossTotal()->getMinorUnits();
        if ($gross <= 0) {
            throw new \DomainException('Factuur zonder positief eindtotaal kan geen betaling ontvangen.');
        }
        $allocated = $allocatedTotal->getMinorUnits();
        if ($allocated <= 0) {
            $status = PaymentStatus::UNPAID;
        } elseif ($allocated < $gross) {
            $status = PaymentStatus::PARTIALLY_PAID;
        } elseif ($allocated === $gross) {
            $status = PaymentStatus::PAID;
        } else {
            $status = PaymentStatus::OVERPAID;
        }

        $statement = $this->connection->prepare(
            'UPDATE `' . self::TABLE . '` SET `payment_status` = :payment_status, `updated_at` = :updated_at '
            . 'WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id AND `status` = :status'
        );
        $statement->execute([
            ':payment_status' => $status,
            ':updated_at' => $updatedAt,
            ':tenant_id' => $invoice->getTenantId()->toString(),
            ':public_id' => $invoice->getPublicId(),
            ':status' => InvoiceStatus::FINALIZED,
        ]);
        return $status;
    }

    public function findPaymentDetailByPublicId(
        TenantId $tenantId,
        string $publicId
    ): ?InvoiceDetailContext {
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }
        $statement = $this->connection->prepare(
            'SELECT `public_id`, `invoice_number`, `status`, `payment_status`, `currency`, `gross_total_minor` '
            . 'FROM `' . self::TABLE . '` WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id LIMIT 1'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':public_id' => $publicId,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row) || empty($row['invoice_number'])) {
            return null;
        }

        return new InvoiceDetailContext(
            (string)$row['public_id'],
            (string)$row['invoice_number'],
            (string)$row['status'],
            (string)$row['payment_status'],
            new Money((int)$row['gross_total_minor'], new Currency((string)$row['currency']))
        );
    }

    private function assertTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Invoice-betaalprojectie vereist een actieve transactie.');
        }
    }
}
