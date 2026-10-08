<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class Quantity
{
    public const SCALE = 4;
    public const DIVISOR = 10000;

    /** @var int */
    private $scaledValue;

    private function __construct(int $scaledValue)
    {
        $this->scaledValue = $scaledValue;
    }

    public static function fromScaled(int $scaledValue): Quantity
    {
        return new self($scaledValue);
    }

    public static function fromDecimal(string $value): Quantity
    {
        $value = trim($value);
        if (preg_match('/^([+-]?)([0-9]+)(?:[,.]([0-9]{1,4}))?$/D', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('Hoeveelheid heeft geen geldige decimale notatie.');
        }

        $fraction = isset($matches[3]) ? str_pad($matches[3], self::SCALE, '0') : '0000';
        $scaled = ltrim($matches[2] . $fraction, '0');
        $scaled = $scaled === '' ? '0' : $scaled;

        if (strlen($scaled) > strlen((string)PHP_INT_MAX)
            || (strlen($scaled) === strlen((string)PHP_INT_MAX) && strcmp($scaled, (string)PHP_INT_MAX) > 0)
        ) {
            throw new \OverflowException('Hoeveelheid valt buiten het ondersteunde integerbereik.');
        }

        $scaledValue = (int)$scaled;
        if ($matches[1] === '-' && $scaledValue !== 0) {
            $scaledValue = -$scaledValue;
        }

        return new self($scaledValue);
    }

    public function getScaledValue(): int
    {
        return $this->scaledValue;
    }

    public function isZero(): bool
    {
        return $this->scaledValue === 0;
    }

    public function format(): string
    {
        if ($this->scaledValue === PHP_INT_MIN) {
            throw new \OverflowException('Hoeveelheid kan niet veilig worden geformatteerd.');
        }

        $negative = $this->scaledValue < 0;
        $absolute = str_pad((string)abs($this->scaledValue), self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($absolute, 0, -self::SCALE);
        $fraction = rtrim(substr($absolute, -self::SCALE), '0');
        $formatted = $fraction === '' ? $whole : $whole . ',' . $fraction;

        return ($negative ? '-' : '') . $formatted;
    }
}
