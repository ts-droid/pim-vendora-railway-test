<?php

namespace App\Services\Allianz;

class AllianzCompanySearch extends AllianzApiService
{
    public function searchCustomer(string $companyName, string $countryCode): ?array
    {
        // PROD: /search/v2/companies/advancedSearch
        $response = $this->makeRequest('POST', '/search/uatm-v2/companies/advancedSearch', [
            'pageSize' => 1,
            'countryCode' => $countryCode,
            'companyName' => $companyName,
            'onlyActive' => true,
        ]);

        return $response['results'][0]['company'] ?? null;
    }
}
