<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class CalendarDate
{
    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches) !== 1
            || !checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])
        ) {
            throw new \InvalidArgumentException('Datum moet een geldige kalenderdatum in YYYY-MM-DD zijn.');
        }

        $this->value = $value;
    }

    public function isBefore(CalendarDate $other): bool
    {
        return strcmp($this->value, $other->value) < 0;
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
