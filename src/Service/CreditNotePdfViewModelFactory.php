<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

final class CreditNotePdfViewModelFactory
{
    public function create(CreditNote $creditNote, array $branding): array
    {
        $lines = [];
        foreach ($creditNote->getLines() as $line) {
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
        foreach ($creditNote->getTotals()->getTaxSummaries() as $summary) {
            $category = $summary->getCategory()->getValue();
            $hasReverseCharge = $hasReverseCharge || $category === TaxCategory::REVERSE_CHARGE;
            $taxRows[] = [
                'label' => $this->taxLabel($category, $summary->getRate()->getBasisPoints()),
                'net' => $this->money($summary->getNet()),
                'tax' => $this->money($summary->getTax()),
            ];
        }

        return [
            // De bestaande sleutel blijft bewust behouden voor compatibiliteit met projecttemplates.
            'invoice' => [
                'document_type' => 'credit_note',
                'title' => 'Creditnota',
                'number' => $creditNote->getNumber(),
                'issue_date' => $this->date($creditNote->getIssueDate()->getValue()),
                'due_date' => '',
                'customer_reference' => '',
                'original_invoice_number' => $creditNote->getOriginalInvoiceNumber(),
                'reason' => $creditNote->getReason(),
                'notes' => '',
                'currency' => $creditNote->getCurrency()->getCode(),
                'customer' => $creditNote->getCustomerSnapshot()->toArray(),
                'address' => $creditNote->getBillingAddressSnapshot()->toArray(),
                'lines' => $lines,
                'tax_rows' => $taxRows,
                'net_total' => $this->money($creditNote->getTotals()->getNet()),
                'tax_total' => $this->money($creditNote->getTotals()->getTax()),
                'gross_total' => $this->money($creditNote->getTotals()->getGross()),
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
