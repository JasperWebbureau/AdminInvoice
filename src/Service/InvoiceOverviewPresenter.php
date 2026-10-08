<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Application\Query\InvoiceListQuery;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListItem;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceListResult;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceOverviewSummary;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Html\Table\TrustedHtml;
use Flexgrid\Utils\_Time;

final class InvoiceOverviewPresenter
{
    /** @var _Time */
    private $clock;

    public function __construct(_Time $clock)
    {
        $this->clock = $clock;
    }

    public function present(
        array $data,
        string $refreshAction,
        string $editBaseUrl = '',
        string $detailBaseUrl = '',
        string $creditNoteBaseUrl = '',
        string $correctIssueDateAction = '',
        string $deleteDraftAction = ''
    ): array
    {
        /** @var InvoiceListQuery $query */
        $query = $data['query'];
        /** @var InvoiceListResult $result */
        $result = $data['result'];
        /** @var InvoiceOverviewSummary[] $summaries */
        $summaries = $data['summaries'];
        if ($summaries === []) {
            $summaries = [new InvoiceOverviewSummary(0, 0, 0, 0, 'EUR')];
        }

        $rows = [];
        foreach ($result->getItems() as $item) {
            $status = $this->status($item);
            $rows[] = [
                'id' => $item->getPublicId(),
                'url' => $this->documentUrl($item, $editBaseUrl, $detailBaseUrl, $creditNoteBaseUrl),
                'state' => $status['tone'] === 'danger' ? 'danger' : '',
                'classes' => $item->isCreditNote() ? ['admin-invoice-row--credit-note'] : [],
                'cells' => [
                    'invoice_number' => [
                        'value' => $item->getInvoiceNumber() !== '' ? $item->getInvoiceNumber() : 'Concept',
                        'secondary' => $item->isCreditNote()
                            ? 'Bronfactuur ' . $item->getOriginalInvoiceNumber()
                            : $item->getCustomerReference(),
                        'title' => true,
                    ],
                    'document_type' => [
                        'value' => $item->isCreditNote() ? 'Creditnota' : 'Factuur',
                        'badge' => $item->isCreditNote() ? 'neutral' : 'info',
                    ],
                    'customer' => $item->getCustomerName() !== '' ? $item->getCustomerName() : 'Onbekende klant',
                    'issue_date' => !$item->isCreditNote() && $correctIssueDateAction !== ''
                        ? new TrustedHtml($this->issueDateField($item, $query, $correctIssueDateAction))
                        : $this->formatDate($item->getIssueDate()),
                    'due_date' => $item->getDueDate() === '' ? '—' : $this->formatDate($item->getDueDate()),
                    'gross_total' => $this->formatMoney($item->getGrossTotalMinor(), $item->getCurrency()),
                    'status' => ['value' => $status['label'], 'badge' => $status['tone']],
                ],
                'actions' => !$item->isCreditNote() && $item->getStatus() === InvoiceStatus::DRAFT && $deleteDraftAction !== ''
                    ? [[
                        'label' => 'Concept verwijderen',
                        'icon' => 'fas fa-trash',
                        'variant' => 'danger',
                        'attributes' => [
                            'ajax' => 'true',
                            'action' => $deleteDraftAction,
                            'data-public-id' => $item->getPublicId(),
                            'alert' => 'Dit concept en alle factuurregels definitief verwijderen?',
                            'use-waiting-icon' => true,
                        ],
                    ]]
                    : [],
            ];
        }

        $columns = [];
        foreach ([
            'invoice_number' => ['Documentnummer', 'left'],
            'document_type' => ['Type', 'left'],
            'customer' => ['Klant', 'left'],
            'issue_date' => ['Datum', 'left'],
            'due_date' => ['Vervaldatum', 'left'],
            'gross_total' => ['Bedrag', 'right'],
            'status' => ['Status', 'left'],
        ] as $key => $settings) {
            $columns[] = [
                'key' => $key,
                'label' => $settings[0],
                'align' => $settings[1],
                'sortable' => $key !== 'document_type',
                'sort_direction' => $query->getSort() === $key ? $query->getDirection() : '',
            ];
        }

        $cards = [];
        $multipleCurrencies = count($summaries) > 1;
        foreach ($summaries as $summary) {
            $currencySuffix = $multipleCurrencies ? ' (' . $summary->getCurrency() . ')' : '';
            $cards[] = ['label' => 'Totaal facturen' . $currencySuffix, 'value' => (string)$summary->getInvoiceCount(), 'meta' => 'in geselecteerd jaar', 'icon' => 'fas fa-file-invoice', 'tone' => 'accent'];
            $cards[] = ['label' => 'Totale omzet' . $currencySuffix, 'value' => $this->formatMoney($summary->getRevenueNetMinor(), $summary->getCurrency()), 'meta' => 'definitief, excl. btw', 'icon' => 'fas fa-euro-sign', 'tone' => 'info'];
            $cards[] = ['label' => 'Betaald' . $currencySuffix, 'value' => $this->formatMoney($summary->getPaidGrossMinor(), $summary->getCurrency()), 'meta' => 'incl. btw', 'icon' => 'fas fa-check', 'tone' => 'success'];
            $cards[] = ['label' => 'Openstaand' . $currencySuffix, 'value' => $this->formatMoney($summary->getOutstandingGrossMinor(), $summary->getCurrency()), 'meta' => 'incl. btw', 'icon' => 'fas fa-clock', 'tone' => 'warning'];
        }

        return [
            'refreshAction' => $refreshAction,
            'query' => $query,
            'result' => $result,
            'years' => $data['years'],
            'summaryCards' => $cards,
            'table' => [
                'id' => 'admin-invoice-overview',
                'label' => 'Facturen en creditnota’s',
                'columns' => $columns,
                'rows' => $rows,
                'empty' => [
                    'title' => 'Geen documenten gevonden',
                    'message' => 'Pas de filters aan of maak een nieuwe factuur.',
                    'icon' => 'fas fa-file-invoice',
                ],
            ],
            'statusOptions' => [
                '' => 'Alle documentstatussen',
                InvoiceStatus::DRAFT => 'Concept',
                InvoiceStatus::FINALIZED => 'Definitief',
                InvoiceStatus::CANCELLED => 'Geannuleerd',
                InvoiceStatus::CREDITED => 'Gecrediteerd',
            ],
            'paymentStatusOptions' => [
                '' => 'Alle betaalstatussen',
                PaymentStatus::UNPAID => 'Openstaand',
                PaymentStatus::PARTIALLY_PAID => 'Deels betaald',
                PaymentStatus::PAID => 'Betaald',
                PaymentStatus::OVERPAID => 'Te veel betaald',
            ],
            'documentTypeOptions' => [
                '' => 'Alle documenten',
                InvoiceListItem::TYPE_INVOICE => 'Facturen',
                InvoiceListItem::TYPE_CREDIT_NOTE => 'Creditnota’s',
            ],
            'pageSizes' => InvoiceListQuery::pageSizes(),
            'pages' => $this->pages($result),
        ];
    }

    private function documentUrl(
        InvoiceListItem $item,
        string $editBaseUrl,
        string $detailBaseUrl,
        string $creditNoteBaseUrl
    ): string
    {
        if ($item->isCreditNote()) {
            $baseUrl = $creditNoteBaseUrl;
        } elseif ($item->getStatus() === InvoiceStatus::DRAFT) {
            $baseUrl = $editBaseUrl;
        } elseif (in_array($item->getStatus(), [InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)) {
            $baseUrl = $detailBaseUrl;
        } else {
            return '';
        }

        return $baseUrl === ''
            ? ''
            : rtrim($baseUrl, '/') . '/' . rawurlencode($item->getPublicId());
    }

    private function status(InvoiceListItem $item): array
    {
        if ($item->isCreditNote()) {
            return ['label' => 'Definitief', 'tone' => 'success'];
        }
        if ($item->getStatus() === InvoiceStatus::DRAFT) {
            return ['label' => 'Concept', 'tone' => 'info'];
        }
        if ($item->getStatus() === InvoiceStatus::CANCELLED) {
            return ['label' => 'Geannuleerd', 'tone' => 'neutral'];
        }
        if ($item->getStatus() === InvoiceStatus::CREDITED) {
            return ['label' => 'Gecrediteerd', 'tone' => 'neutral'];
        }
        if ($item->getPaymentStatus() === PaymentStatus::PAID) {
            return ['label' => 'Betaald', 'tone' => 'success'];
        }
        if ($item->getPaymentStatus() === PaymentStatus::OVERPAID) {
            return ['label' => 'Te veel betaald', 'tone' => 'info'];
        }
        if ($item->getPaymentStatus() === PaymentStatus::PARTIALLY_PAID) {
            return ['label' => 'Deels betaald', 'tone' => 'warning'];
        }
        if ($item->getDueDate() < date('Y-m-d', (int)$this->clock->get())) {
            return ['label' => 'Verlopen', 'tone' => 'danger'];
        }

        return ['label' => 'Openstaand', 'tone' => 'warning'];
    }

    private function formatMoney(int $minorUnits, string $currencyCode): string
    {
        $money = new Money($minorUnits, new Currency($currencyCode));
        $symbol = $currencyCode === 'EUR' ? '€' : $currencyCode;

        return $symbol . ' ' . $money->format();
    }

    private function formatDate(string $date): string
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return $date;
        }

        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }

    private function issueDateField(InvoiceListItem $item, InvoiceListQuery $query, string $action): string
    {
        $attributes = [
            'type' => 'date',
            'name' => 'issue_date',
            'value' => $item->getIssueDate(),
            'ajax' => 'onchange',
            'action' => $action,
            'data-public_id' => $item->getPublicId(),
            'data-view' => 'overview',
            'aria-label' => 'Factuurdatum aanpassen',
        ] + $this->queryAttributes($query);
        $html = '<input';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return $html . '>';
    }

    private function queryAttributes(InvoiceListQuery $query): array
    {
        return [
            'data-q' => $query->getSearch(),
            'data-status' => $query->getStatus(),
            'data-payment_status' => $query->getPaymentStatus(),
            'data-year' => $query->getYear() ?? '',
            'data-sort' => $query->getSort(),
            'data-direction' => $query->getDirection(),
            'data-page' => $query->getPage(),
            'data-per_page' => $query->getPerPage(),
            'data-document_type' => $query->getDocumentType(),
        ];
    }

    /** @return int[] */
    private function pages(InvoiceListResult $result): array
    {
        $total = $result->getTotalPages();
        $start = max(1, $result->getPage() - 2);
        $end = min($total, $start + 4);
        $start = max(1, $end - 4);

        return range($start, $end);
    }
}
