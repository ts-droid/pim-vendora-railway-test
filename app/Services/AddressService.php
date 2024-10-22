<?php

namespace App\Services;

use App\Models\Address;

class AddressService
{
    public function createAddress(array $data): Address
    {
        return Address::create([
            'street_line_1' => $data['street_line_1'] ?? null,
            'street_line_2' => $data['street_line_2'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country_code' => isset($data['country_code']) ? $this->formatCountryCode($data['country_code']) : null,
        ]);
    }

    private function formatCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new \Exception('Invalid country code format');
        }

        return $countryCode;
    }
}
