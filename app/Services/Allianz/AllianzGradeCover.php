<?php

namespace App\Services\Allianz;

class AllianzGradeCover extends AllianzApiService
{
    public function new()
    {
        $policies = [];

        $contracts = config('allianz.contracts');
        foreach ($contracts as $contract) {
            $policies[] = [
                'businessUnitCode' => $contract['code'],
                'policyId' => $contract['policy_id'],
                'extensionId' => $contract['extension'],
            ];
        }

        // PROD: /riskinfo/v3/covers/search
        $response = $this->makeRequest('POST', '/uatm/riskinfo/v3/covers/search', [
            'policies' => $policies,
            'pagination' => [
                'page' => 1,
                'pageSize' => 10000,
                'isTotalRequired' => true,
            ]
        ]);

        dd($response);
    }

    public function requestGradeCover(string $companyID): ?string
    {
        $contract = config('allianz.contracts.contract_1');

        // PROD: /riskinfo/v3/covers
        $jobID = $this->makeRequest('POST', '/uatm/riskinfo/v3/covers', [
            'requestTypeCode' => 'GradeCover',
            'policy' => [
                'businessUnitCode' => $contract['code'],
                'policyId' => $contract['policy_id'],
                'extensionId' => $contract['extension'],
            ],
            'companyId' => $companyID,
            'isRequestUrgent' => false,
        ]);

        return $jobID ?: null;
    }

    public function checkStatus(string $jobID)
    {
        // PROD: /riskinfo/v3/jobs/{jobId}
        $response = $this->makeRequest('GET', '/uatm/riskinfo/v3/jobs/' . $jobID);

        /*if ($response['jobStatusCode'] !== 'PROCESSED') {
            dd($response);
        }*/

        dd($response);
    }
}
