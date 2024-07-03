<?php

namespace App\Services\Allianz;

use App\Models\Customer;

class AllianzGradeCover extends AllianzApiService
{
    public function getCustomerGrade(Customer $customer): int
    {
        return \App\Models\AllianzGradeCover::select('grade')
            ->where('customer_id', '=', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->first()->grade ?? 0;
    }

    public function getCustomerGradeData(Customer $customer): array
    {
        $grades = \App\Models\AllianzGradeCover::where('customer_id', '=', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->get();

        $currentGrade = $grades->first();
        $lastGrade = $grades->skip(1)->first();

        return [
            'grade' => $currentGrade->grade ?? null,
            'last_grade' => $lastGrade->grade ?? null,
            'last_grade_change' => $currentGrade ? date('Y-m-d', strtotime($currentGrade->created_at)) : null,
            'history' => array_map(function($item) {
                return [
                    'grade' => $item['grade'],
                    'date' => date('Y-m-d', strtotime($item['created_at'])),
                ];
            }, $grades->toArray()),
        ];
    }

    public function importGradeCover(): void
    {
        $results = $this->gradeSearch();

        if (!$results) {
            return;
        }

        foreach ($results as $result) {
            if (($result['coverStatusCode'] ?? '') != 'CheckPolicy') {
                continue;
            }

            $this->importGradeData([
                'company_id' => $result['companyId'] ?? '',
                'company_name' => $result['companyName'] ?? '',
                'company_country_code' => $result['companyCountryCode'] ?? '',
                'grade' => (int) ($result['gradePolicyCoverGradeCode'] ?? ''),
            ]);
        }
    }

    public function gradeSearch(): array
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

        $results = $this->makeRequest('POST', 'riskinfo_cover_search', [
            'policies' => $policies,
            'pagination' => [
                'page' => 1,
                'pageSize' => 10000,
                'isTotalRequired' => true,
            ]
        ]);

        return $results ?: [];
    }

    private function importGradeData(array $gradeData): void
    {
        $customerSearch = new AllianzCustomerSearch();
        $customer = $customerSearch->getLocalCustomer($gradeData['company_id'], $gradeData['company_name']);

        if (!$customer) {
            return;
        }

        $this->storeGrade($customer, $gradeData['grade']);
    }

    private function storeGrade(Customer $customer, int $grade)
    {
        // Check if the grade is different from currently stored grade
        $currentGrade = \App\Models\AllianzGradeCover::where('customer_id', '=', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->first()->grade ?? 0;

        if ($currentGrade == $grade) {
            return;
        }

        \App\Models\AllianzGradeCover::create([
            'customer_id' => $customer->id,
            'grade' => $grade,
        ]);
    }
}
