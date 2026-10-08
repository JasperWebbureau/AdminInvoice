<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

final class InvoiceEditorPresenter
{
    /** @var InvoiceCalculator */
    private $calculator;

    public function __construct(InvoiceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function present(Invoice $invoice, array $actions): array
    {
        $totals = $invoice->getFinalizedTotals() ?: $this->calculator->calculate($invoice);
        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $lines[] = [
                'public_id' => $line->getPublicId(),
                'description' => $line->getDescription(),
                'catalog_item_public_id' => $line->getCatalogItemPublicId(),
                'quantity' => $line->getQuantity()->format(),
                'unit' => $line->getUnit(),
                'unit_price' => $line->getUnitPrice()->format(',', ''),
                'tax_rate' => $this->percentage($line->getTaxRate()->getBasisPoints()),
                'tax_category' => $line->getTaxCategory()->getValue(),
                'tax_label' => $this->taxLabel(
                    $line->getTaxCategory()->getValue(),
                    $line->getTaxRate()->getBasisPoints()
                ),
                'tax_choice' => $this->percentage($line->getTaxRate()->getBasisPoints()) . '|' . $line->getTaxCategory()->getValue(),
                'net' => $this->money($line->calculateNet()),
                'gross' => $this->money($line->calculateGross()),
            ];
        }

        $taxRows = [];
        foreach ($totals->getTaxSummaries() as $summary) {
            $taxRows[] = [
                'label' => $this->taxLabel(
                    $summary->getCategory()->getValue(),
                    $summary->getRate()->getBasisPoints()
                ),
                'value' => $this->money($summary->getTax()),
            ];
        }

        return [
            'invoice' => $invoice,
            'publicId' => $invoice->getPublicId(),
            'displayNumber' => $invoice->getInvoiceNumber() ?: 'Concept',
            'statusLabel' => $this->statusLabel($invoice->getStatus()->getValue()),
            'finalizedAt' => $invoice->getFinalizedAt() === null
                ? ''
                : date('d-m-Y H:i', $invoice->getFinalizedAt()),
            'canFinalize' => $invoice->getStatus()->getValue() === 'draft' && $invoice->getLines() !== [],
            'customer' => $invoice->getCustomerSnapshot()->toArray(),
            'address' => $invoice->getBillingAddressSnapshot()->toArray(),
            'lines' => $lines,
            'taxRows' => $taxRows,
            'netTotal' => $this->money($totals->getNet()),
            'grossTotal' => $this->money($totals->getGross()),
            'actions' => $actions,
            'taxOptions' => [
                ['rate' => '21', 'category' => TaxCategory::STANDARD, 'label' => '21%'],
                ['rate' => '9', 'category' => TaxCategory::REDUCED, 'label' => '9%'],
                ['rate' => '0', 'category' => TaxCategory::ZERO, 'label' => '0%'],
                ['rate' => '0', 'category' => TaxCategory::REVERSE_CHARGE, 'label' => 'Verlegd'],
                ['rate' => '0', 'category' => TaxCategory::EXEMPT, 'label' => 'Vrijgesteld'],
            ],
        ];
    }

    private function money(Money $money): string
    {
        $prefix = $money->getCurrency()->getCode() === 'EUR'
            ? '€ '
            : $money->getCurrency()->getCode() . ' ';

        return $prefix . $money->format();
    }

    private function statusLabel(string $status): string
    {
        if ($status === InvoiceStatus::DRAFT) {
            return 'Concept';
        }
        if ($status === InvoiceStatus::FINALIZED) {
            return 'Definitief';
        }
        if ($status === InvoiceStatus::CANCELLED) {
            return 'Geannuleerd';
        }

        return 'Gecrediteerd';
    }

    private function percentage(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = $basisPoints % 100;

        return $fraction === 0 ? (string)$whole : $whole . ',' . rtrim(str_pad((string)$fraction, 2, '0', STR_PAD_LEFT), '0');
    }

    private function taxLabel(string $category, int $basisPoints): string
    {
        if ($category === TaxCategory::REVERSE_CHARGE) {
            return 'Btw verlegd';
        }
        if ($category === TaxCategory::EXEMPT) {
            return 'Vrijgesteld';
        }

        return 'Btw (' . $this->percentage($basisPoints) . '%)';
    }
}
