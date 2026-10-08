<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class AddressSnapshot
{
    public const SCHEMA_VERSION = 1;

    /** @var string */
    private $line1;
    /** @var string */
    private $line2;
    /** @var string */
    private $postalCode;
    /** @var string */
    private $city;
    /** @var string */
    private $region;
    /** @var string */
    private $countryCode;

    public function __construct(
        string $line1,
        string $postalCode,
        string $city,
        string $countryCode,
        string $line2 = '',
        string $region = ''
    ) {
        $line1 = trim($line1);
        $postalCode = trim($postalCode);
        $city = trim($city);
        $countryCode = strtoupper(trim($countryCode));
        if ($line1 === '' || $postalCode === '' || $city === '') {
            throw new \InvalidArgumentException('Adresregel, postcode en plaats zijn verplicht.');
        }
        if (preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1) {
            throw new \InvalidArgumentException('Landcode moet uit twee letters bestaan.');
        }

        $this->line1 = $line1;
        $this->line2 = trim($line2);
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->region = trim($region);
        $this->countryCode = $countryCode;
    }

    public static function fromArray(array $data): AddressSnapshot
    {
        if ((int)($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException('Onbekende adressnapshotversie.');
        }

        return new self(
            (string)($data['line_1'] ?? ''),
            (string)($data['postal_code'] ?? ''),
            (string)($data['city'] ?? ''),
            (string)($data['country_code'] ?? ''),
            (string)($data['line_2'] ?? ''),
            (string)($data['region'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'line_1' => $this->line1,
            'line_2' => $this->line2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'region' => $this->region,
            'country_code' => $this->countryCode,
        ];
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }
}
