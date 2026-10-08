<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class PaymentStatus
{
    public const UNPAID = 'unpaid';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const OVERPAID = 'overpaid';

    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException('Ongeldige betaalstatus.');
        }

        $this->value = $value;
    }

    /** @return string[] */
    public static function values(): array
    {
        return [self::UNPAID, self::PARTIALLY_PAID, self::PAID, self::OVERPAID];
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
