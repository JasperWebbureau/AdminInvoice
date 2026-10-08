<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Integration\Report;

use Flexgrid\Database\Connection;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminReport\Contract\ReportProviderInterface;
use Flexgrid\Modules\AdminReport\Contract\ReportPeriodListProviderInterface;
use Flexgrid\Modules\AdminReport\ValueObject\ReportPeriod;

final class ReportProvider implements ReportProviderInterface, ReportPeriodListProviderInterface
{
    private $tenant;
    private $connection;

    public function __construct(TenantId $tenant, ?\PDO $connection = null)
    {
        $this->tenant = $tenant;
        $this->connection = $connection ?: Connection::getConnections();
    }

    public function getReportContribution(ReportPeriod $period, bool $includeTransactions = true): array
    {
        $parameters = [':tenant' => $this->tenant->toString(), ':start' => $period->getStartDate(), ':end' => $period->getEndDate()];
        $documentSql = "SELECT `currency`,LEFT(`issue_date`,7) report_month,`net_total_minor` revenue_net_minor,`tax_total_minor` output_tax_minor FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `status` IN ('final','credited') AND `issue_date` BETWEEN :start AND :end UNION ALL SELECT `currency`,LEFT(`issue_date`,7) report_month,`net_total_minor` revenue_net_minor,`tax_total_minor` output_tax_minor FROM `admin_credit_note` WHERE `tenant_id`=:tenant_credit AND `issue_date` BETWEEN :start_credit AND :end_credit";
        $aggregate = $this->connection->prepare('SELECT `currency`,`report_month`,SUM(`revenue_net_minor`) revenue_net_minor,SUM(`output_tax_minor`) output_tax_minor FROM (' . $documentSql . ') documents GROUP BY `currency`,`report_month` ORDER BY `currency`,`report_month`');
        $aggregate->execute($parameters + [':tenant_credit' => $parameters[':tenant'], ':start_credit' => $parameters[':start'], ':end_credit' => $parameters[':end']]);
        $currencies = [];
        foreach ($aggregate->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $code = (string)$row['currency']; $month = (string)$row['report_month'];
            if (!isset($currencies[$code])) { $currencies[$code] = $this->emptyCurrency(); }
            $revenue = (int)$row['revenue_net_minor']; $tax = (int)$row['output_tax_minor'];
            $currencies[$code]['revenue_net_minor'] += $revenue;
            $currencies[$code]['output_tax_minor'] += $tax;
            $currencies[$code]['months'][$month] = ['revenue_net_minor' => $revenue, 'output_tax_minor' => $tax];
        }

        $transactions = [];
        if ($includeTransactions) {
            $transactionsSql = "SELECT * FROM (SELECT CONCAT('invoice-',`public_id`) id,'income' entry_type,`public_id`,`invoice_number` number,`customer_snapshot`,`issue_date`, `net_total_minor` net_minor,`tax_total_minor` tax_minor,`currency`,2 type_order FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `status` IN ('final','credited') AND `issue_date` BETWEEN :start AND :end UNION ALL SELECT CONCAT('credit-',`public_id`) id,'credit' entry_type,`public_id`,`credit_note_number` number,`customer_snapshot`,`issue_date`,`net_total_minor` net_minor,`tax_total_minor` tax_minor,`currency`,1 type_order FROM `admin_credit_note` WHERE `tenant_id`=:tenant_credit AND `issue_date` BETWEEN :start_credit AND :end_credit) entries ORDER BY `issue_date` DESC,`type_order`,`number` DESC LIMIT 20";
            $statement = $this->connection->prepare($transactionsSql);
            $statement->execute($parameters + [':tenant_credit' => $parameters[':tenant'], ':start_credit' => $parameters[':start'], ':end_credit' => $parameters[':end']]);
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $credit = $row['entry_type'] === 'credit';
                $transactions[] = ['id'=>(string)$row['id'],'date'=>(string)$row['issue_date'],'title'=>($credit?'Creditnota ':'Factuur ').(string)$row['number'],'description'=>$this->name($row['customer_snapshot']),'type'=>$credit?'Creditnota':'Inkomst','tone'=>$credit?'warning':'success','net_minor'=>(int)$row['net_minor'],'tax_minor'=>(int)$row['tax_minor'],'currency'=>(string)$row['currency'],'href'=>$this->url($credit?'creditNote/':'detail/',(string)$row['public_id']),'timestamp'=>(int)strtotime((string)$row['issue_date'].' 12:00:00')];
            }
        }

        $years = $this->connection->prepare("SELECT report_year FROM (SELECT LEFT(`issue_date`,4) report_year FROM `admin_invoice` WHERE `tenant_id`=:tenant UNION SELECT LEFT(`issue_date`,4) report_year FROM `admin_credit_note` WHERE `tenant_id`=:tenant_credit) years ORDER BY report_year DESC");
        $years->execute([':tenant'=>$this->tenant->toString(),':tenant_credit'=>$this->tenant->toString()]);
        return ['currencies'=>$currencies,'transactions'=>$transactions,'years'=>array_map('intval',$years->fetchAll(\PDO::FETCH_COLUMN))];
    }

    public function getSummaryContribution(ReportPeriod $period): array
    {
        return $this->getReportContribution($period, false);
    }

    public function getPeriodList(ReportPeriod $period, string $kind): array
    {
        if ($kind !== 'bookings') {
            return [];
        }
        $sql = "SELECT * FROM (SELECT CONCAT('invoice-',`public_id`) id,'invoice' entry_type,`public_id`,`invoice_number` number,`customer_snapshot`,`issue_date`,`net_total_minor` amount_minor,`tax_total_minor` tax_minor,`currency` FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `status` IN ('final','credited') AND `issue_date` BETWEEN :start AND :end UNION ALL SELECT CONCAT('credit-',`public_id`) id,'credit' entry_type,`public_id`,`credit_note_number` number,`customer_snapshot`,`issue_date`,`net_total_minor` amount_minor,`tax_total_minor` tax_minor,`currency` FROM `admin_credit_note` WHERE `tenant_id`=:credit_tenant AND `issue_date` BETWEEN :credit_start AND :credit_end) entries ORDER BY `issue_date` DESC,`id` DESC";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            ':tenant' => $this->tenant->toString(),
            ':start' => $period->getStartDate(),
            ':end' => $period->getEndDate(),
            ':credit_tenant' => $this->tenant->toString(),
            ':credit_start' => $period->getStartDate(),
            ':credit_end' => $period->getEndDate(),
        ]);
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $credit = $row['entry_type'] === 'credit';
            $rows[] = [
                'id' => (string)$row['id'],
                'date' => (string)$row['issue_date'],
                'title' => ($credit ? 'Creditnota ' : 'Factuur ') . (string)$row['number'],
                'description' => $this->name($row['customer_snapshot']),
                'type' => $credit ? 'Creditnota' : 'Factuur',
                'tone' => $credit ? 'warning' : 'success',
                'amount_minor' => (int)$row['amount_minor'],
                'tax_minor' => (int)$row['tax_minor'],
                'currency' => (string)$row['currency'],
                'href' => $this->url($credit ? 'creditNote/' : 'detail/', (string)$row['public_id']),
            ];
        }
        return $rows;
    }

    private function emptyCurrency(): array { return ['revenue_net_minor'=>0,'costs_net_minor'=>0,'output_tax_minor'=>0,'input_tax_minor'=>0,'months'=>[],'categories'=>[]]; }
    private function name($snapshot): string { $data=json_decode((string)$snapshot,true); return is_array($data)&&trim((string)($data['name']??''))!==''?trim((string)$data['name']):'Onbekende klant'; }
    private function url(string $path,string $id): string { $domain=defined('__DOMAIN__')?rtrim((string)constant('__DOMAIN__'),'/'):''; return $domain.'/Flexgrid/AdminInvoice/'.$path.rawurlencode($id); }
}
