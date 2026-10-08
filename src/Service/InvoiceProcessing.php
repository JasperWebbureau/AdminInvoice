<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCollectionExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;

/** Persisteert workflowclaims; financiële mutaties blijven bij de bestaande use-cases. */
final class InvoiceProcessing
{
    private $connection;
    private $tenant;
    private $getInvoice;
    private $finalize;
    private $dispatch;
    private $collection;
    private $automatic;

    public function __construct(\PDO $connection, TenantId $tenant, callable $getInvoice,
        callable $finalize, InvoiceDispatchService $dispatch,
        ?InvoiceCollectionExtensionInterface $collection, callable $automatic)
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->getInvoice = $getInvoice;
        $this->finalize = $finalize;
        $this->dispatch = $dispatch;
        $this->collection = $collection;
        $this->automatic = $automatic;
    }

    public function enroll(Invoice $invoice): void
    {
        if (!$invoice->getTenantId()->equals($this->tenant) || $invoice->getSource() !== 'admin_recurring') {
            return;
        }
        if ($this->find($invoice->getPublicId()) !== null) { return; }
        $created = $this->connection->prepare('SELECT `created_at` FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `public_id`=:invoice');
        $created->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoice->getPublicId()]);
        $createdAt = (int)$created->fetchColumn();
        $statement = $this->connection->prepare('INSERT INTO `admin_invoice_processing` '
            . '(`tenant_id`,`invoice_public_id`,`status`,`automatic_approval`,`created_at`,`updated_at`) '
            . 'VALUES (:tenant,:invoice,:status,:automatic,:created,:updated)');
        try {
            $statement->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoice->getPublicId(),
                ':status' => 'review', ':automatic' => ($this->automatic)() && $createdAt >= time() - 10 ? 1 : 0,
                ':created' => time(), ':updated' => time()]);
        } catch (\PDOException $exception) {
            if ($this->find($invoice->getPublicId()) === null) { throw $exception; }
        }
    }

    public function find(string $invoiceId): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM `admin_invoice_processing` '
            . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice');
        $statement->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoiceId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function run(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->connection->prepare('SELECT * FROM `admin_invoice_processing` '
            . 'WHERE `tenant_id`=:tenant AND `status`=:status ORDER BY `updated_at`,`created_at` LIMIT ' . $limit);
        $statement->execute([':tenant' => $this->tenant->toString(), ':status' => 'review']);
        $result = ['sent' => 0, 'review' => 0, 'failed' => 0];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $id = (string)$row['invoice_public_id'];
            $claimed = false;
            try {
                $invoice = ($this->getInvoice)($id);
                if ($invoice->getStatus()->getValue() === InvoiceStatus::DRAFT
                    && (!(bool)$row['automatic_approval'] || !($this->automatic)())) {
                    $result['review']++;
                    $this->status($id, 'review', '', 'review');
                    continue;
                }
                $claim = $this->connection->prepare('UPDATE `admin_invoice_processing` SET `status`=:processing,`updated_at`=:now '
                    . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice AND `status`=:review');
                $claim->execute([':processing' => 'processing', ':now' => time(), ':tenant' => $this->tenant->toString(),
                    ':invoice' => $id, ':review' => 'review']);
                if ($claim->rowCount() !== 1) { continue; }
                $claimed = true;
                if ($invoice->getStatus()->getValue() === InvoiceStatus::DRAFT) {
                    if (!filter_var($invoice->getCustomerSnapshot()->toArray()['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \DomainException('Vul een geldig factuur-e-mailadres in voordat de factuur automatisch wordt goedgekeurd.');
                    }
                    $invoice = ($this->finalize)($id);
                }
                if ($invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED) {
                    throw new \DomainException('Deze factuur kan niet meer automatisch worden verwerkt.');
                }
                $sent = false;
                $latest = null;
                foreach ($this->dispatch->history($id) as $attempt) {
                    if ($attempt['kind'] !== 'invoice') { continue; }
                    $latest = $attempt;
                    if ($attempt['status'] === 'sent') { $sent = true; }
                }
                if ($latest !== null && ($latest['status'] === 'pending' || !$sent)) {
                    throw new \DomainException('Een eerdere verzending vereist controle voordat deze workflow verdergaat.');
                }
                $notice = $this->collection !== null ? $this->collection->prepare($invoice) : '';
                if (!$sent) {
                    $this->dispatch->send($invoice, 'invoice', (string)$invoice->getCustomerSnapshot()->toArray()['email'], false, $notice);
                } elseif ($notice !== '') {
                    // A manually sent invoice may not have contained the collection notice.
                    throw new \DomainException('De factuur is al verzonden. Plan de incasso via het incassopaneel met een afzonderlijke aankondiging.');
                }
                if ($this->collection !== null) { $this->collection->noticeSent($invoice); }
                $this->status($id, 'sent', '');
                $result['sent']++;
            } catch (\Throwable $exception) {
                $this->status($id, 'failed', $exception->getMessage(), $claimed ? 'processing' : 'review');
                $result['failed']++;
            }
        }
        return $result;
    }

    public function overview(): array
    {
        $statement = $this->connection->prepare('SELECT p.*,i.`invoice_number`,i.`status` invoice_status,i.`customer_snapshot` '
            . 'FROM `admin_invoice_processing` p LEFT JOIN `admin_invoice` i ON i.`tenant_id`=p.`tenant_id` '
            . 'AND i.`public_id`=p.`invoice_public_id` WHERE p.`tenant_id`=:tenant ORDER BY p.`created_at` DESC LIMIT 100');
        $statement->execute([':tenant' => $this->tenant->toString()]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function resume(string $id): void
    {
        $row = $this->find($id);
        if ($row === null || !in_array($row['status'], ['failed', 'processing'], true)
            || ($row['status'] === 'processing' && (int)$row['updated_at'] > time() - 900)) {
            throw new \DomainException('Deze verwerking kan nu niet worden hervat.');
        }
        foreach ($this->dispatch->history($id) as $attempt) {
            if ($attempt['kind'] === 'invoice' && $attempt['status'] === 'pending') {
                throw new \DomainException('Controleer de mailprovider: de uitkomst van een verzending is nog onbekend.');
            }
        }
        $this->status($id, 'review', '');
    }

    public function completeAfterDispatch(string $id): void
    {
        if ($this->find($id) === null) { return; }
        foreach ($this->dispatch->history($id) as $attempt) {
            if ($attempt['kind'] === 'invoice' && $attempt['status'] === 'sent') { $this->status($id, 'sent', ''); return; }
        }
        throw new \DomainException('De factuur is nog niet als verzonden geregistreerd.');
    }

    private function status(string $id, string $status, string $error, ?string $expected = null): void
    {
        $statement = $this->connection->prepare('UPDATE `admin_invoice_processing` SET `status`=:status,`error`=:error,`updated_at`=:now '
            . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice'
            . ($expected !== null ? ' AND `status`=:expected' : ''));
        $params = [':status' => $status, ':error' => $error !== '' ? substr($error, 0, 1000) : null,
            ':now' => time(), ':tenant' => $this->tenant->toString(), ':invoice' => $id];
        if ($expected !== null) { $params[':expected'] = $expected; }
        $statement->execute($params);
    }
}
