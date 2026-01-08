<?php

namespace App\Services\Allianz;

use App\Models\Customer;

class AllianzCustomerSearch extends AllianzApiService
{
    public function getCompany(string $companyID): ?array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $result = $this->makeRequest('POST', 'find_companies', [$companyID]);
        return $result['companies'][0] ?? null;
    }

    public function getLocalCustomer(string $companyID, string $companyName): ?Customer
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // First try to check existing company id
        $customer = Customer::where('allianz_company_id', $companyID)->first();
        if ($customer) {
            return $customer;
        }

        // Try to match against name
        $customer = Customer::where('name', $companyName)->first();
        if ($customer) {
            // Save the company id for future reference
            if ($companyID) {
                $customer->update(['allianz_company_id' => $companyID]);
            }

            return $customer;
        }

        // Search against the allianz API to get more company data
        if ($companyID) {
            $company = $this->getCompany($companyID);
            if ($company) {

                // Try to match all identifiers against the stored vat number
                $companyIdentifiers = $company['companyIdentifiers'] ?? [];
                foreach ($companyIdentifiers as $companyIdentifier) {
                    $idValue = $companyIdentifier['idValue'] ?? '';
                    if (!$idValue) {
                        continue;
                    }

                    $customer = Customer::where('vat_number', 'LIKE', '%' . $idValue . '%')->first();
                    if ($customer) {
                        // Save the company id for future reference
                        $customer->update(['allianz_company_id' => $companyID]);

                        return $customer;
                    }
                }

            }
        }

        return null;
    }
}
