<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCustomerProviderInterface;

final class InvoiceCustomerPrefill
{
    public function resolve(?InvoiceCustomerProviderInterface $provider, string $publicId): array
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return $this->emptyResult('');
        }
        if ($provider === null) {
            return $this->emptyResult('De gekoppelde klantenmodule is niet beschikbaar. Vul de klantgegevens handmatig in.');
        }

        $selection = $provider->find($publicId);
        if ($selection === null) {
            return $this->emptyResult('De klant is niet actief of niet beschikbaar. Vul de gegevens handmatig in.');
        }

        $party = $selection->getParty();
        $address = $selection->getBillingAddress();
        return [
            'customer' => [
                'source_public_id' => $selection->getPublicId(),
                'name' => (string)($party['name'] ?? ''),
                'contact_name' => (string)($party['contact_name'] ?? ''),
                'email' => (string)($party['email'] ?? ''),
                'phone' => (string)($party['phone'] ?? ''),
                'registration_number' => (string)($party['registration_number'] ?? ''),
                'tax_number' => (string)($party['tax_number'] ?? ''),
            ],
            'address' => [
                'line_1' => (string)($address['line_1'] ?? ''),
                'line_2' => (string)($address['line_2'] ?? ''),
                'postal_code' => (string)($address['postal_code'] ?? ''),
                'city' => (string)($address['city'] ?? ''),
                'region' => (string)($address['region'] ?? ''),
                'country_code' => (string)($address['country_code'] ?? 'NL'),
            ],
            'notice' => '',
        ];
    }

    private function emptyResult(string $notice): array
    {
        return [
            'customer' => [
                'source_public_id' => '',
                'name' => '',
                'contact_name' => '',
                'email' => '',
                'phone' => '',
                'registration_number' => '',
                'tax_number' => '',
            ],
            'address' => [
                'line_1' => '',
                'line_2' => '',
                'postal_code' => '',
                'city' => '',
                'region' => '',
                'country_code' => 'NL',
            ],
            'notice' => $notice,
        ];
    }
}
