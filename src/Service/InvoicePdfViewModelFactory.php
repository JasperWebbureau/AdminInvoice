<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

final class InvoicePdfViewModelFactory
{
    public function create(Invoice $invoice, array $branding): array
    {
        if (!in_array($invoice->getStatus()->getValue(), [InvoiceStatus::FINALIZED, InvoiceStatus::CREDITED], true)
            || $invoice->getFinalizedTotals() === null
            || $invoice->getInvoiceNumber() === null
        ) {
            throw new \DomainException('Alleen een definitieve factuur kan als PDF worden gegenereerd.');
        }

        return $this->build($invoice, $invoice->getFinalizedTotals(), $branding, false);
    }

    public function createPreview(Invoice $invoice, array $branding): array
    {
        if ($invoice->getStatus()->getValue() !== InvoiceStatus::DRAFT) {
            throw new \DomainException('Alleen een conceptfactuur kan als concept-PDF worden bekeken.');
        }

        return $this->build($invoice, (new InvoiceCalculator())->calculate($invoice), $branding, true);
    }

    private function build(Invoice $invoice, InvoiceTotals $totals, array $branding, bool $isPreview): array
    {
        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $lines[] = [
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity()->format(),
                'unit' => $line->getUnit(),
                'unit_price' => $this->money($line->getUnitPrice()),
                'tax_label' => $this->taxLabel(
                    $line->getTaxCategory()->getValue(),
                    $line->getTaxRate()->getBasisPoints()
                ),
                'net' => $this->money($line->calculateNet()),
                'tax' => $this->money($line->calculateTax()),
                'gross' => $this->money($line->calculateGross()),
            ];
        }

        $taxRows = [];
        $hasReverseCharge = false;
        foreach ($totals->getTaxSummaries() as $summary) {
            $category = $summary->getCategory()->getValue();
            $hasReverseCharge = $hasReverseCharge || $category === TaxCategory::REVERSE_CHARGE;
            $taxRows[] = [
                'label' => $this->taxLabel($category, $summary->getRate()->getBasisPoints()),
                'net' => $this->money($summary->getNet()),
                'tax' => $this->money($summary->getTax()),
            ];
        }

        return [
            'invoice' => [
                'document_type' => 'invoice',
                'is_preview' => $isPreview,
                'title' => $isPreview ? 'Conceptfactuur' : 'Factuur',
                'number' => $isPreview ? '' : (string)$invoice->getInvoiceNumber(),
                'status' => $invoice->getStatus()->getValue(),
                'issue_date' => $this->date($invoice->getIssueDate()->getValue()),
                'due_date' => $this->date($invoice->getDueDate()->getValue()),
                'customer_reference' => $invoice->getCustomerReference(),
                'original_invoice_number' => '',
                'reason' => '',
                'notes' => $invoice->getNotes(),
                'currency' => $invoice->getCurrency()->getCode(),
                'customer' => $invoice->getCustomerSnapshot()->toArray(),
                'address' => $invoice->getBillingAddressSnapshot()->toArray(),
                'lines' => $lines,
                'tax_rows' => $taxRows,
                'net_total' => $this->money($totals->getNet()),
                'tax_total' => $this->money($totals->getTax()),
                'gross_total' => $this->money($totals->getGross()),
                'has_reverse_charge' => $hasReverseCharge,
            ],
            'company' => is_array($branding['company'] ?? null) ? $branding['company'] : [],
            'brand' => is_array($branding['brand'] ?? null) ? $branding['brand'] : [],
        ];
    }

    private function money(Money $money): string
    {
        $prefix = $money->getCurrency()->getCode() === 'EUR'
            ? '€ '
            : $money->getCurrency()->getCode() . ' ';

        return $prefix . $money->format();
    }

    private function date(string $value): string
    {
        $parts = explode('-', $value);
        return count($parts) === 3 ? $parts[2] . '-' . $parts[1] . '-' . $parts[0] : $value;
    }

    private function taxLabel(string $category, int $basisPoints): string
    {
        if ($category === TaxCategory::REVERSE_CHARGE) {
            return 'Btw verlegd';
        }
        if ($category === TaxCategory::EXEMPT) {
            return 'Vrijgesteld';
        }

        $whole = intdiv($basisPoints, 100);
        $fraction = $basisPoints % 100;
        $percentage = $fraction === 0
            ? (string)$whole
            : $whole . ',' . rtrim(str_pad((string)$fraction, 2, '0', STR_PAD_LEFT), '0');

        return 'Btw ' . $percentage . '%';
    }
}
