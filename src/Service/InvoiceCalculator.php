<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceTotals;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxSummary;

final class InvoiceCalculator
{
    public function calculate(Invoice $invoice): InvoiceTotals
    {
        return $this->calculateLines($invoice->getCurrency(), $invoice->getLines());
    }

    /** @param \Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine[] $lines */
    public function calculateLines(Currency $currency, array $lines): InvoiceTotals
    {
        $net = Money::zero($currency);
        $tax = Money::zero($currency);
        $groups = [];

        foreach ($lines as $line) {
            $lineNet = $line->calculateNet();
            $lineTax = $line->calculateTax();
            $net = $net->add($lineNet);
            $tax = $tax->add($lineTax);

            $key = $line->getTaxCategory()->getValue() . ':' . $line->getTaxRate()->getBasisPoints();
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'category' => $line->getTaxCategory(),
                    'rate' => $line->getTaxRate(),
                    'net' => Money::zero($currency),
                    'tax' => Money::zero($currency),
                ];
            }
            $groups[$key]['net'] = $groups[$key]['net']->add($lineNet);
            $groups[$key]['tax'] = $groups[$key]['tax']->add($lineTax);
        }

        $summaries = [];
        foreach ($groups as $group) {
            $summaries[] = new TaxSummary($group['category'], $group['rate'], $group['net'], $group['tax']);
        }
        usort($summaries, function (TaxSummary $left, TaxSummary $right): int {
            $rateComparison = $left->getRate()->getBasisPoints() <=> $right->getRate()->getBasisPoints();
            if ($rateComparison !== 0) {
                return $rateComparison;
            }

            return strcmp($left->getCategory()->getValue(), $right->getCategory()->getValue());
        });

        return new InvoiceTotals($net, $tax, $net->add($tax), $summaries);
    }
}
