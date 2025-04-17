<?php

namespace App\Services;

use App\Models\Address;

class AddressService
{
    public function createAddress(array $data): Address
    {
        return Address::create([
            'full_name' => $data['full_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'street_line_1' => $data['street_line_1'] ?? null,
            'street_line_2' => $data['street_line_2'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country_code' => isset($data['country_code']) ? $this->formatCountryCode($data['country_code']) : null,
        ]);
    }

    public function updateAddress(Address $address, array $data): Address
    {
        $updateData = [];

        if (isset($data['full_name'])) {
            $updateData['full_name'] = $data['full_name'];
        }
        if (isset($data['first_name'])) {
            $updateData['first_name'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $updateData['last_name'] = $data['last_name'];
        }
        if (isset($data['street_line_1'])) {
            $updateData['street_line_1'] = $data['street_line_1'];
        }
        if (isset($data['street_line_2'])) {
            $updateData['street_line_2'] = $data['street_line_2'];
        }
        if (isset($data['postal_code'])) {
            $updateData['postal_code'] = $data['postal_code'];
        }
        if (isset($data['city'])) {
            $updateData['city'] = $data['city'];
        }
        if (isset($data['country_code'])) {
            $updateData['country_code'] = $this->formatCountryCode($data['country_code']);
        }

        if ($updateData) {
            $address->update($updateData);
        }

        return $address;
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
