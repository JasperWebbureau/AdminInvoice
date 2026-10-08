<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Domain\ValueObject;

final class PartySnapshot
{
    public const SCHEMA_VERSION = 2;

    /** @var string */
    private $name;
    /** @var string */
    private $contactName;
    /** @var string */
    private $email;
    /** @var string */
    private $phone;
    /** @var string */
    private $registrationNumber;
    /** @var string */
    private $taxNumber;
    /** @var string */
    private $sourcePublicId;

    public function __construct(
        string $name,
        string $contactName = '',
        string $email = '',
        string $phone = '',
        string $registrationNumber = '',
        string $taxNumber = '',
        string $sourcePublicId = ''
    ) {
        $name = trim($name);
        $email = trim($email);
        $sourcePublicId = trim($sourcePublicId);
        if ($name === '') {
            throw new \InvalidArgumentException('Snapshotnaam is verplicht.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Snapshot-e-mailadres is ongeldig.');
        }
        if (strlen($sourcePublicId) > 64) {
            throw new \InvalidArgumentException('Publieke bron-id is maximaal 64 tekens.');
        }

        $this->name = $name;
        $this->contactName = trim($contactName);
        $this->email = $email;
        $this->phone = trim($phone);
        $this->registrationNumber = trim($registrationNumber);
        $this->taxNumber = trim($taxNumber);
        $this->sourcePublicId = $sourcePublicId;
    }

    public static function fromArray(array $data): PartySnapshot
    {
        $version = (int)($data['schema_version'] ?? 0);
        if (!in_array($version, [1, self::SCHEMA_VERSION], true)) {
            throw new \InvalidArgumentException('Onbekende party-snapshotversie.');
        }

        return new self(
            (string)($data['name'] ?? ''),
            (string)($data['contact_name'] ?? ''),
            (string)($data['email'] ?? ''),
            (string)($data['phone'] ?? ''),
            (string)($data['registration_number'] ?? ''),
            (string)($data['tax_number'] ?? ''),
            $version >= 2 ? (string)($data['source_public_id'] ?? '') : ''
        );
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'name' => $this->name,
            'contact_name' => $this->contactName,
            'email' => $this->email,
            'phone' => $this->phone,
            'registration_number' => $this->registrationNumber,
            'tax_number' => $this->taxNumber,
            'source_public_id' => $this->sourcePublicId,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSourcePublicId(): string
    {
        return $this->sourcePublicId;
    }
}
