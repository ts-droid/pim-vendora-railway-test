<?php

namespace App\Services\VismaNet;

use App\Models\Supplier;

class VismaNetSupplierService extends VismaNetApiService
{
    public function createSupplier(Supplier $supplier): array
    {
        $payload = [
            'number' => ['value' => $supplier->number],
            'name' => ['value' => $supplier->name],
            'status' => ['value' => 'Active'],
        ];

        if ($supplier->class) {
            $payload['supplierClassId'] = ['value' => $supplier->class];
        }
        if ($supplier->credit_terms) {
            $payload['creditTermsId'] = ['value' => $supplier->credit_terms];
        }
        if ($supplier->language) {
            $payload['documentLanguage'] = ['value' => $supplier->language];
        }
        if ($supplier->currency) {
            $payload['currencyId'] = ['value' => $supplier->currency];
        }
        if ($supplier->vat_number) {
            $payload['vatRegistrationId'] = ['value' => $supplier->vat_number];
        }
        if ($supplier->org_number) {
            $payload['corporateId'] = ['value' => $supplier->org_number];
        }

        // TODO: Add "mainAddress"

        // TODO: Add "mainContact"

        // TODO: Add "remitAddress"

        // TODO: Add "remitContact"

        // TODO: Add "supplierAddress"

        // TODO: Add "supplierContact"

        $response = $this->callAPI('POST', '/v1/supplier', $payload);
        if (!$response['success']) {
            return [
                'success' => false,
                'error_message' => 'Failed to create supplier in Visma.net',
            ];
        }

        return [
            'success' => true,
            'error_message' => null
        ];
    }
}
