<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class InvoiceStatus
{
    public const DRAFT = 'draft';
    public const FINALIZED = 'final';
    public const CANCELLED = 'cancelled';
    public const CREDITED = 'credited';

    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException('Ongeldige factuurstatus.');
        }

        $this->value = $value;
    }

    /** @return string[] */
    public static function values(): array
    {
        return [self::DRAFT, self::FINALIZED, self::CANCELLED, self::CREDITED];
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(InvoiceStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
