<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;

final class TaxSummary
{
    /** @var TaxCategory */
    private $category;
    /** @var TaxRate */
    private $rate;
    /** @var Money */
    private $net;
    /** @var Money */
    private $tax;

    public function __construct(TaxCategory $category, TaxRate $rate, Money $net, Money $tax)
    {
        if (!$net->getCurrency()->equals($tax->getCurrency())) {
            throw new \InvalidArgumentException('Btw-samenvatting vereist een gelijke valuta.');
        }

        $this->category = $category;
        $this->rate = $rate;
        $this->net = $net;
        $this->tax = $tax;
    }

    public function getCategory(): TaxCategory
    {
        return $this->category;
    }

    public function getRate(): TaxRate
    {
        return $this->rate;
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
        return $this->net->add($this->tax);
    }
}
