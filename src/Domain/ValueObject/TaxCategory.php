<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class TaxCategory
{
    public const STANDARD = 'standard';
    public const REDUCED = 'reduced';
    public const ZERO = 'zero';
    public const REVERSE_CHARGE = 'reverse_charge';
    public const EXEMPT = 'exempt';

    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException('Ongeldige btw-categorie.');
        }

        $this->value = $value;
    }

    /** @return string[] */
    public static function values(): array
    {
        return [self::STANDARD, self::REDUCED, self::ZERO, self::REVERSE_CHARGE, self::EXEMPT];
    }

    public function requiresZeroRate(): bool
    {
        return in_array($this->value, [self::ZERO, self::REVERSE_CHARGE, self::EXEMPT], true);
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
