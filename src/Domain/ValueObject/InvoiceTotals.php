<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

use Flexgrid\Modules\AdminCore\ValueObject\Money;

final class InvoiceTotals
{
    /** @var Money */
    private $net;
    /** @var Money */
    private $tax;
    /** @var Money */
    private $gross;
    /** @var TaxSummary[] */
    private $taxSummaries;

    /** @param TaxSummary[] $taxSummaries */
    public function __construct(Money $net, Money $tax, Money $gross, array $taxSummaries)
    {
        if (!$net->add($tax)->equals($gross)) {
            throw new \InvalidArgumentException('Brutototaal moet gelijk zijn aan netto plus btw.');
        }

        $summaryNet = Money::zero($net->getCurrency());
        $summaryTax = Money::zero($net->getCurrency());
        foreach ($taxSummaries as $summary) {
            if (!$summary instanceof TaxSummary) {
                throw new \InvalidArgumentException('Ongeldige btw-samenvatting.');
            }
            $summaryNet = $summaryNet->add($summary->getNet());
            $summaryTax = $summaryTax->add($summary->getTax());
        }
        if (!$summaryNet->equals($net) || !$summaryTax->equals($tax)) {
            throw new \InvalidArgumentException('Btw-groepen sluiten niet aan op de factuurtotalen.');
        }

        $this->net = $net;
        $this->tax = $tax;
        $this->gross = $gross;
        $this->taxSummaries = array_values($taxSummaries);
    }

    public function getNet(): Money
    {
        return $this->net;
    }

    public function getTax(): Money
    {
        return $this->tax;
    }

    public function getGross(): Money
    {
        return $this->gross;
    }

    /** @return TaxSummary[] */
    public function getTaxSummaries(): array
    {
        return $this->taxSummaries;
    }
}
