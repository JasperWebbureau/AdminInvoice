<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceOverviewSummary;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceListRepositoryInterface;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerHistoryRepositoryInterface;

final class PdoInvoiceListRepository implements InvoiceListRepositoryInterface, InvoiceCustomerHistoryRepositoryInterface
{
    private const INVOICE_TABLE = 'admin_invoice';
    private const CREDIT_NOTE_TABLE = 'admin_credit_note';

    /** @var \PDO */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function search(TenantId $tenantId, InvoiceListQuery $query): InvoiceListResult
    {
        list($where, $parameters) = $this->buildWhere($tenantId, $query);
        $source = $this->documentSourceSql();
        $count = $this->connection->prepare('SELECT COUNT(*) FROM ' . $source . ' WHERE ' . $where);
        $count->execute($parameters);
        $total = (int)$count->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $query->getPerPage()));
        $page = min($query->getPage(), $totalPages);
        $offset = ($page - 1) * $query->getPerPage();

        $sortColumns = [
            'invoice_number' => '`document_number`',
            'customer' => '`customer_name`',
            'issue_date' => '`issue_date`',
            'due_date' => '`due_date`',
            'gross_total' => '`gross_total_minor`',
            'status' => '`status`',
        ];
        $orderBy = $sortColumns[$query->getSort()] . ' ' . strtoupper($query->getDirection());

        $statement = $this->connection->prepare(
            'SELECT `public_id`, `document_type`, `document_number`, `customer_reference`, '
            . '`customer_snapshot`, `customer_name`, `issue_date`, `due_date`, `gross_total_minor`, '
            . '`currency`, `status`, `payment_status`, `original_invoice_number` '
            . 'FROM ' . $source . ' WHERE ' . $where . ' '
            . 'ORDER BY ' . $orderBy . ', `document_type` ASC, `id` DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $query->getPerPage(), \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = $this->hydrateItem($row);
        }

        return new InvoiceListResult($items, $total, $page, $query->getPerPage());
    }

    public function findRecentByCustomer(TenantId $tenantId, string $customerPublicId, int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT `public_id`, `document_type`, `document_number`, `customer_reference`, '
            . '`customer_snapshot`, `customer_name`, `issue_date`, `due_date`, `gross_total_minor`, '
            . '`currency`, `status`, `payment_status`, `original_invoice_number` '
            . 'FROM ' . $this->documentSourceSql() . ' '
            . 'WHERE JSON_UNQUOTE(JSON_EXTRACT(`customer_snapshot`, \'$.source_public_id\')) = :customer_public_id '
            . 'ORDER BY `issue_date` DESC, `document_type` ASC, `id` DESC LIMIT :limit'
        );
        $statement->bindValue(':invoice_tenant_id', $tenantId->toString(), \PDO::PARAM_STR);
        $statement->bindValue(':credit_tenant_id', $tenantId->toString(), \PDO::PARAM_STR);
        $statement->bindValue(':customer_public_id', $customerPublicId, \PDO::PARAM_STR);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = $this->hydrateItem($row);
        }
        return $items;
    }

    public function getSummaries(TenantId $tenantId, ?int $year = null, ?int $quarter = null): array
    {
        $where = '`tenant_id` = :tenant_id';
        $parameters = [':tenant_id' => $tenantId->toString()];
        if ($year !== null) {
            if ($quarter === null) {
                $where .= ' AND `issue_date` LIKE :year';
                $parameters[':year'] = $year . '-%';
            } else {
                $month = ($quarter - 1) * 3 + 1;
                $where .= ' AND `issue_date` >= :period_start AND `issue_date` < :period_end';
                $parameters[':period_start'] = sprintf('%04d-%02d-01', $year, $month);
                $parameters[':period_end'] = $month === 10 ? sprintf('%04d-01-01', $year + 1) : sprintf('%04d-%02d-01', $year, $month + 3);
            }
        }

        $statement = $this->connection->prepare(
            'SELECT `currency`, COUNT(*) AS `invoice_count`, '
            . "COALESCE(SUM(CASE WHEN `status` = 'final' THEN `net_total_minor` ELSE 0 END), 0) AS `revenue_net`, "
            . "COALESCE(SUM(CASE WHEN `status` = 'final' AND `payment_status` IN ('paid', 'overpaid') THEN `gross_total_minor` ELSE 0 END), 0) AS `paid_gross`, "
            . "COALESCE(SUM(CASE WHEN `status` = 'final' AND `payment_status` IN ('unpaid', 'partially_paid') THEN `gross_total_minor` ELSE 0 END), 0) AS `outstanding_gross` "
            . 'FROM `' . self::INVOICE_TABLE . '` WHERE ' . $where . ' GROUP BY `currency` ORDER BY `currency`'
        );
        $statement->execute($parameters);

        $summaries = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $summaries[] = new InvoiceOverviewSummary(
                (int)$row['invoice_count'],
                (int)$row['revenue_net'],
                (int)$row['paid_gross'],
                (int)$row['outstanding_gross'],
                (string)$row['currency']
            );
        }

        return $summaries;
    }

    public function getAvailableYears(TenantId $tenantId): array
    {
        $statement = $this->connection->prepare(
            'SELECT `document_year` AS `invoice_year` FROM ('
            . 'SELECT LEFT(`issue_date`, 4) AS `document_year` FROM `' . self::INVOICE_TABLE . '` WHERE `tenant_id` = :invoice_tenant '
            . 'UNION '
            . 'SELECT LEFT(`issue_date`, 4) AS `document_year` FROM `' . self::CREDIT_NOTE_TABLE . '` WHERE `tenant_id` = :credit_tenant'
            . ') AS `document_years` ORDER BY `document_year` DESC'
        );
        $statement->execute([
            ':invoice_tenant' => $tenantId->toString(),
            ':credit_tenant' => $tenantId->toString(),
        ]);

        $years = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $year = (int)$row['invoice_year'];
            if ($year >= 1900 && $year <= 2200) {
                $years[] = $year;
            }
        }

        return $years;
    }

    /** @return array{0:string,1:array<string,string>} */
    private function buildWhere(TenantId $tenantId, InvoiceListQuery $query): array
    {
        $parts = ['1 = 1'];
        $parameters = [
            ':invoice_tenant_id' => $tenantId->toString(),
            ':credit_tenant_id' => $tenantId->toString(),
        ];
        if ($query->getSearch() !== '') {
            $parts[] = "CONCAT_WS(' ', COALESCE(`document_number`, ''), COALESCE(`customer_reference`, ''), "
                . "COALESCE(`search_text`, ''), COALESCE(`original_invoice_number`, ''), `customer_snapshot`) LIKE :search";
            $parameters[':search'] = '%' . $query->getSearch() . '%';
        }
        if ($query->getStatus() !== '') {
            $parts[] = '`status` = :status';
            $parameters[':status'] = $query->getStatus();
        }
        if ($query->getPaymentStatus() !== '') {
            $parts[] = '`payment_status` = :payment_status';
            $parameters[':payment_status'] = $query->getPaymentStatus();
        }
        if ($query->getYear() !== null) {
            if ($query->getQuarter() === null) {
                $parts[] = '`issue_date` LIKE :year';
                $parameters[':year'] = $query->getYear() . '-%';
            } else {
                $month = ($query->getQuarter() - 1) * 3 + 1;
                $parts[] = '`issue_date` >= :period_start';
                $parts[] = '`issue_date` < :period_end';
                $parameters[':period_start'] = sprintf('%04d-%02d-01', $query->getYear(), $month);
                $parameters[':period_end'] = $month === 10 ? sprintf('%04d-01-01', $query->getYear() + 1) : sprintf('%04d-%02d-01', $query->getYear(), $month + 3);
            }
        }
        if ($query->getDocumentType() !== '') {
            $parts[] = '`document_type` = :document_type';
            $parameters[':document_type'] = $query->getDocumentType();
        }

        return [implode(' AND ', $parts), $parameters];
    }

    private function documentSourceSql(): string
    {
        return '('
            . 'SELECT `id`, `tenant_id`, \'invoice\' AS `document_type`, `public_id`, '
            . 'COALESCE(`invoice_number`, \'\') AS `document_number`, COALESCE(`customer_reference`, \'\') AS `customer_reference`, '
            . '`customer_snapshot`, JSON_UNQUOTE(JSON_EXTRACT(`customer_snapshot`, \'$.name\')) AS `customer_name`, '
            . '`issue_date`, `due_date`, `gross_total_minor`, `currency`, `status`, `payment_status`, '
            . 'COALESCE(`notes`, \'\') AS `search_text`, \'\' AS `original_invoice_number` '
            . 'FROM `' . self::INVOICE_TABLE . '` WHERE `tenant_id` = :invoice_tenant_id '
            . 'UNION ALL '
            . 'SELECT `id`, `tenant_id`, \'credit_note\' AS `document_type`, `public_id`, '
            . '`credit_note_number` AS `document_number`, \'\' AS `customer_reference`, '
            . '`customer_snapshot`, JSON_UNQUOTE(JSON_EXTRACT(`customer_snapshot`, \'$.name\')) AS `customer_name`, '
            . '`issue_date`, \'\' AS `due_date`, `gross_total_minor`, `currency`, \'final\' AS `status`, '
            . '\'\' AS `payment_status`, `reason` AS `search_text`, `original_invoice_number` '
            . 'FROM `' . self::CREDIT_NOTE_TABLE . '` WHERE `tenant_id` = :credit_tenant_id'
            . ') AS `documents`';
    }

    private function hydrateItem(array $row): InvoiceListItem
    {
        $snapshot = json_decode((string)$row['customer_snapshot'], true);
        if (!is_array($snapshot) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \UnexpectedValueException('Factuurlijst bevat een ongeldige klantsnapshot.');
        }
        return new InvoiceListItem(
            (string)$row['public_id'],
            (string)($row['document_number'] ?? ''),
            trim((string)($snapshot['name'] ?? '')),
            (string)($row['customer_reference'] ?? ''),
            (string)$row['issue_date'],
            (string)$row['due_date'],
            (int)$row['gross_total_minor'],
            (string)$row['currency'],
            (string)$row['status'],
            (string)$row['payment_status'],
            (string)$row['document_type'],
            (string)$row['original_invoice_number']
        );
    }
}
