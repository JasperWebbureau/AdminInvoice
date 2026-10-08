<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Integration\Dashboard;

use Flexgrid\Modules\AdminDashboard\Contract\DashboardProviderInterface;
use Flexgrid\Modules\AdminDashboard\Provider\AbstractPdoDashboardProvider;

final class DashboardProvider extends AbstractPdoDashboardProvider implements DashboardProviderInterface
{
    public function getDashboardContribution(): array
    {
        $tenant = $this->tenant->toString();
        $today = $this->clock->format('Y-m-d');
        $allocationJoin = '';
        $outstanding = 'i.`gross_total_minor`';
        if ($this->hasAllocationTable()) {
            $allocationJoin = " LEFT JOIN `admin_payment_allocation` a ON a.`tenant_id`=i.`tenant_id` AND a.`target_type`='invoice' AND a.`target_public_id`=i.`public_id` ";
            $outstanding = 'GREATEST(i.`gross_total_minor`-COALESCE(SUM(a.`amount_minor`),0),0)';
        }

        $open = $this->connection->prepare("SELECT i.`currency`,COUNT(*) invoice_count,SUM(outstanding_minor) outstanding_minor FROM (SELECT i.*,{$outstanding} outstanding_minor FROM `admin_invoice` i {$allocationJoin} WHERE i.`tenant_id`=:tenant AND i.`status`='final' AND i.`payment_status` IN ('unpaid','partially_paid') GROUP BY i.`id`) i WHERE outstanding_minor>0 GROUP BY i.`currency` ORDER BY i.`currency`");
        $open->execute([':tenant' => $tenant]);
        $openRows = $open->fetchAll(\PDO::FETCH_ASSOC);
        $openCount = array_sum(array_map(function (array $row): int { return (int)$row['invoice_count']; }, $openRows));

        $revenue = $this->connection->prepare("SELECT `currency`,COUNT(*) invoice_count,COALESCE(SUM(`net_total_minor`),0) revenue_minor FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `status`='final' AND `issue_date` LIKE :month GROUP BY `currency` ORDER BY `currency`");
        $revenue->execute([':tenant' => $tenant, ':month' => $this->clock->format('Y-m') . '-%']);
        $revenueRows = $revenue->fetchAll(\PDO::FETCH_ASSOC);
        $revenueCount = array_sum(array_map(function (array $row): int { return (int)$row['invoice_count']; }, $revenueRows));

        $overdue = $this->connection->prepare("SELECT i.`currency`,COUNT(*) invoice_count,SUM(outstanding_minor) outstanding_minor FROM (SELECT i.*,{$outstanding} outstanding_minor FROM `admin_invoice` i {$allocationJoin} WHERE i.`tenant_id`=:tenant AND i.`status`='final' AND i.`payment_status` IN ('unpaid','partially_paid') AND i.`due_date`<:today GROUP BY i.`id`) i WHERE outstanding_minor>0 GROUP BY i.`currency` ORDER BY i.`currency`");
        $overdue->execute([':tenant' => $tenant, ':today' => $today]);
        $overdueRows = $overdue->fetchAll(\PDO::FETCH_ASSOC);
        $overdueCount = array_sum(array_map(function (array $row): int { return (int)$row['invoice_count']; }, $overdueRows));

        $list = $this->connection->prepare("SELECT i.*,{$outstanding} outstanding_minor FROM `admin_invoice` i {$allocationJoin} WHERE i.`tenant_id`=:tenant AND i.`status`='final' AND i.`payment_status` IN ('unpaid','partially_paid') GROUP BY i.`id` HAVING outstanding_minor>0 ORDER BY i.`due_date`,i.`id` DESC LIMIT 5");
        $list->execute([':tenant' => $tenant]);
        $rows = [];
        foreach ($list->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
            $late = (string)$invoice['due_date'] < $today;
            $partial = (string)$invoice['payment_status'] === 'partially_paid';
            $rows[] = [
                'id' => (string)$invoice['public_id'],
                'url' => $this->url('AdminInvoice', 'detail/' . rawurlencode((string)$invoice['public_id'])),
                'state' => $late ? 'danger' : '',
                'cells' => [
                    (string)($invoice['invoice_number'] ?: 'Concept'),
                    $this->snapshotName($invoice['customer_snapshot']),
                    $this->dateLabel((string)$invoice['due_date']),
                    $this->money((int)$invoice['outstanding_minor'], (string)$invoice['currency']),
                    ['value' => $late ? 'Te laat' : ($partial ? 'Deels betaald' : 'Open'), 'badge' => $late ? 'danger' : ($partial ? 'warning' : 'info')],
                ],
            ];
        }

        $activityStatement = $this->connection->prepare("SELECT `public_id`,`invoice_number`,`status`,`customer_snapshot`,`updated_at` FROM `admin_invoice` WHERE `tenant_id`=:tenant ORDER BY `updated_at` DESC,`id` DESC LIMIT 3");
        $activityStatement->execute([':tenant' => $tenant]);
        $activities = [];
        foreach ($activityStatement->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
            $number = (string)($invoice['invoice_number'] ?: 'Conceptfactuur');
            $activities[] = [
                'id' => 'invoice-' . $invoice['public_id'],
                'title' => $number,
                'description' => ($invoice['status'] === 'final' ? 'Factuur bijgewerkt voor ' : 'Concept bijgewerkt voor ') . $this->snapshotName($invoice['customer_snapshot']),
                'icon' => 'fas fa-file-invoice',
                'tone' => $invoice['status'] === 'final' ? 'success' : 'info',
                'href' => $this->url('AdminInvoice', ($invoice['status'] === 'draft' ? 'edit/' : 'detail/') . rawurlencode((string)$invoice['public_id'])),
                'timestamp' => (int)$invoice['updated_at'],
                'time' => $this->activityTime((int)$invoice['updated_at']),
            ];
        }

        $attention = [];
        if ($overdueCount > 0) {
            $attention[] = ['id' => 'overdue-invoices', 'title' => $overdueCount . ' factuur' . ($overdueCount === 1 ? '' : 'en') . ' te laat', 'description' => $this->aggregateMoney($overdueRows, 'outstanding_minor') . ' staat voorbij de vervaldatum open.', 'icon' => 'fas fa-triangle-exclamation', 'tone' => 'danger', 'badge' => 'Te laat', 'badge_tone' => 'danger', 'href' => $this->url('AdminInvoice', 'invoices?status=final&payment_status=unpaid'), 'priority' => 10];
        }

        $agendaStatement = $this->connection->prepare("SELECT `public_id`,`invoice_number`,`customer_snapshot`,`due_date` FROM `admin_invoice` WHERE `tenant_id`=:tenant AND `status`='final' AND `payment_status` IN ('unpaid','partially_paid') AND `due_date`>=:today AND `due_date`<=:until ORDER BY `due_date`,`id` LIMIT 3");
        $agendaStatement->execute([':tenant' => $tenant, ':today' => $today, ':until' => date('Y-m-d', strtotime($today . ' +30 days'))]);
        $agenda = [];
        foreach ($agendaStatement->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
            $agenda[] = $this->agendaDate((string)$invoice['due_date']) + ['id' => 'invoice-due-' . $invoice['public_id'], 'title' => 'Factuur vervalt', 'description' => (string)($invoice['invoice_number'] ?: 'Factuur') . ' · ' . $this->snapshotName($invoice['customer_snapshot']), 'time' => 'Gehele dag', 'href' => $this->url('AdminInvoice', 'detail/' . rawurlencode((string)$invoice['public_id']))];
        }

        return [
            'summary_cards' => [
                ['id' => 'open-invoices', 'label' => 'Openstaande facturen', 'value' => $this->aggregateMoney($openRows, 'outstanding_minor'), 'meta' => $openCount . ' factuur' . ($openCount === 1 ? '' : 'en'), 'icon' => 'fas fa-file-invoice', 'tone' => 'info', 'href' => $this->url('AdminInvoice', 'invoices?status=final&payment_status=unpaid')],
                ['id' => 'monthly-revenue', 'label' => 'Omzet deze maand', 'value' => $this->aggregateMoney($revenueRows, 'revenue_minor'), 'meta' => $revenueCount . ' definitieve factuur' . ($revenueCount === 1 ? '' : 'en'), 'icon' => 'fas fa-chart-bar', 'tone' => 'success', 'href' => $this->url('AdminInvoice', 'invoices?status=final&year=' . $this->clock->format('Y'))],
                ['id' => 'overdue-invoices', 'label' => 'Te laat betaald', 'value' => (string)$overdueCount, 'meta' => $this->aggregateMoney($overdueRows, 'outstanding_minor'), 'icon' => 'fas fa-exclamation-circle', 'tone' => $overdueCount > 0 ? 'danger' : 'success', 'href' => $this->url('AdminInvoice', 'invoices?status=final&payment_status=unpaid')],
            ],
            'activities' => $activities,
            'quick_actions' => [['id' => 'create-invoice', 'label' => 'Nieuwe factuur', 'icon' => 'fas fa-file-circle-plus', 'tone' => 'info', 'href' => $this->url('AdminInvoice', 'create'), 'priority' => 10],
                ['id' => 'invoice-processing', 'label' => 'Factuurverwerking', 'icon' => 'fas fa-gears', 'tone' => 'info', 'href' => $this->url('AdminInvoiceProcessing', 'index'), 'priority' => 25]],
            'attention' => $attention,
            'tables' => [[
                'id' => 'open-invoices', 'title' => 'Openstaande facturen', 'icon' => 'fas fa-file-invoice', 'columns' => ['Nummer', 'Klant', 'Vervaldatum', 'Openstaand', 'Status'], 'rows' => $rows,
                'empty' => 'Er staan geen definitieve facturen open.', 'href' => $this->url('AdminInvoice', 'invoices'), 'priority' => 10,
            ]],
            'modules' => [['id' => 'admin-invoice', 'title' => 'Facturen', 'description' => $openCount . ' openstaand', 'icon' => 'fas fa-file-invoice', 'status' => 'Actief', 'tone' => 'info', 'href' => $this->url('AdminInvoice', 'invoices'), 'priority' => 40]],
            'agenda' => $agenda,
        ];
    }

    private function hasAllocationTable(): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $statement->execute([':table' => 'admin_payment_allocation']);
        return (int)$statement->fetchColumn() > 0;
    }
}
