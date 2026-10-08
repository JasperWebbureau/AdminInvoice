<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;

final class CustomerInvoiceHistoryPresenter
{
    /** @var InvoiceOverviewPresenter */ private $overview;

    public function __construct(InvoiceOverviewPresenter $overview)
    {
        $this->overview = $overview;
    }

    public function present(
        array $items,
        string $editBaseUrl,
        string $detailBaseUrl,
        string $creditNoteBaseUrl
    ): array {
        $viewModel = $this->overview->present([
            'query' => new InvoiceListQuery('', '', '', null, 'issue_date', 'desc', 1, 10),
            'result' => new InvoiceListResult($items, count($items), 1, 10),
            'summaries' => [],
            'years' => [],
        ], '', $editBaseUrl, $detailBaseUrl, $creditNoteBaseUrl);

        $table = $viewModel['table'];
        $table['id'] = 'admin-customer-invoice-history';
        $table['label'] = 'Recente facturen en creditnota’s';
        $table['compact'] = true;
        $table['columns'] = array_values(array_filter($table['columns'], function (array $column): bool {
            return in_array($column['key'], ['invoice_number', 'document_type', 'issue_date', 'gross_total', 'status'], true);
        }));
        foreach ($table['columns'] as &$column) {
            $column['sortable'] = false;
            $column['sort_direction'] = '';
        }
        unset($column);
        $table['empty'] = [
            'title' => 'Nog geen documenten',
            'message' => 'Voor deze klant zijn nog geen facturen of creditnota’s vastgelegd.',
            'icon' => 'fas fa-file-invoice',
        ];

        return $table;
    }
}
