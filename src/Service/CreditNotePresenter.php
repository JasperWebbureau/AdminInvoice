<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Domain\Model\CreditNote;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;

final class CreditNotePresenter
{
    public function present(CreditNote $creditNote): array
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
                'gross' => $this->money($line->calculateGross()),
            ];
        }

        $taxRows = [];
        foreach ($creditNote->getTotals()->getTaxSummaries() as $summary) {
            $taxRows[] = [
                'label' => $this->taxLabel(
                    $summary->getCategory()->getValue(),
                    $summary->getRate()->getBasisPoints()
                ),
                'value' => $this->money($summary->getTax()),
            ];
        }

        return [
            'creditNote' => $creditNote,
            'displayNumber' => $creditNote->getNumber(),
            'customer' => $creditNote->getCustomerSnapshot()->toArray(),
            'address' => $creditNote->getBillingAddressSnapshot()->toArray(),
            'lines' => $lines,
            'taxRows' => $taxRows,
            'netTotal' => $this->money($creditNote->getTotals()->getNet()),
            'grossTotal' => $this->money($creditNote->getTotals()->getGross()),
            'finalizedAt' => date('d-m-Y H:i', $creditNote->getFinalizedAt()),
        ];
    }

    private function money(Money $money): string
    {
        $prefix = $money->getCurrency()->getCode() === 'EUR'
            ? '€ '
            : $money->getCurrency()->getCode() . ' ';

        return $prefix . $money->format();
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

        return 'Btw (' . $percentage . '%)';
    }
}
