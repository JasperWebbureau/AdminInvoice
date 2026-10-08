<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

final class CreditNoteNumberFormatter
{
    public function format(string $periodKey, int $sequenceValue): string
    {
        $periodKey = trim($periodKey);
        if (preg_match('/^[0-9]{4}$/D', $periodKey) !== 1 || $sequenceValue < 1) {
            throw new \InvalidArgumentException('Creditnotanummering vereist een geldig jaar en positieve reekswaarde.');
        }

        return 'CN-' . $periodKey . '-' . str_pad((string)$sequenceValue, 4, '0', STR_PAD_LEFT);
    }
}
