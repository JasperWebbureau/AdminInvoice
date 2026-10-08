<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Application\ReadModel;

final class InvoiceCustomerSelection
{
    /** @var string */ private $publicId;
    /** @var array */ private $party;
    /** @var array */ private $billingAddress;

    public function __construct(string $publicId, array $party, array $billingAddress)
    {
        $publicId = trim($publicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Klantselectie vereist een geldige publieke id.');
        }
        foreach (['name'] as $required) {
            if (!isset($party[$required]) || trim((string)$party[$required]) === '') {
                throw new \InvalidArgumentException('Klantselectie mist verplichte klantgegevens.');
            }
        }
        foreach (['line_1', 'postal_code', 'city', 'country_code'] as $required) {
            if (!isset($billingAddress[$required]) || trim((string)$billingAddress[$required]) === '') {
                throw new \InvalidArgumentException('Klantselectie mist een volledig factuuradres.');
            }
        }
        $this->publicId = $publicId;
        $this->party = $party;
        $this->billingAddress = $billingAddress;
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getParty(): array { return $this->party; }
    public function getBillingAddress(): array { return $this->billingAddress; }
}
