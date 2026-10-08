<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;

/** Small tenant-scoped read model for concepts awaiting human review. */
final class InvoiceReviewQueue
{
    private $connection;
    private $tenant;

    public function __construct(\PDO $connection, TenantId $tenant)
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
    }

    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $plans = [];
        $planStatement = $this->connection->prepare(
            'SELECT `public_id`,`name` FROM `admin_invoice_recurring` WHERE `tenant_id`=:tenant'
        );
        $planStatement->execute([':tenant' => $this->tenant->toString()]);
        foreach ($planStatement->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $plans[$plan['public_id']] = $plan['name'];
        }
        $statement = $this->connection->prepare(
            'SELECT `public_id`,`customer_snapshot`,`source`,`external_id`,`issue_date`,'
            . '`gross_total_minor`,`currency`,`created_at` FROM `admin_invoice` '
            . 'WHERE `tenant_id`=:tenant AND `status`=:status '
            . 'ORDER BY `created_at` DESC,`id` DESC LIMIT ' . $limit
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':status' => 'draft']);
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $customer = json_decode((string)$row['customer_snapshot'], true);
            $row['customer_name'] = is_array($customer) ? (string)($customer['name'] ?? '') : '';
            $planId = explode(':', (string)$row['external_id'], 2)[0];
            $row['origin'] = $row['source'] === 'admin_recurring'
                ? 'Herhaalschema: ' . (string)($plans[$planId] ?? 'niet meer beschikbaar')
                : ($row['source'] === 'admin_quote' ? 'Geaccepteerde offerte'
                    : ($row['source'] === '' ? 'Handmatig' : 'Andere koppeling'));
            $row['amount'] = number_format((int)$row['gross_total_minor'] / 100, 2, ',', '.');
            $rows[] = $row;
        }
        return $rows;
    }
}
