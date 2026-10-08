<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

final class InvoiceNumberFormatter
{
    public function format(string $periodKey, int $sequenceValue): string
    {
        $periodKey = trim($periodKey);
        if (preg_match('/^[0-9]{4}$/D', $periodKey) !== 1) {
            throw new \InvalidArgumentException('Factuurnummerperiode moet een viercijferig jaar zijn.');
        }
        if ($sequenceValue < 1) {
            throw new \InvalidArgumentException('Factuurnummerreeks moet positief zijn.');
        }

        return $periodKey . '-' . str_pad((string)$sequenceValue, 4, '0', STR_PAD_LEFT);
    }
}
