<?php

namespace App\Services\Models;

use App\Models\CustomerPayment;
use App\Models\CustomerPaymentLine;

class CustomerPaymentService
{
    public function addCustomerPayment(array $paymentData)
    {
        $customerPayment = CustomerPayment::where('reference_number', $paymentData['reference_number'])->first();

        if ($customerPayment) {
            $customerPayment::update([
                'status' => $paymentData['status'],
                'customer_number' => $paymentData['customer_number'],
                'application_date' => $paymentData['application_date'],
                'payment_reference' => $paymentData['payment_reference'],
                'currency' => $paymentData['currency'],
                'payment_amount' => $paymentData['payment_amount'],
            ]);
        }
        else {
            $customerPayment = CustomerPayment::create([
                'reference_number' => $paymentData['reference_number'],
                'status' => $paymentData['status'],
                'customer_number' => $paymentData['customer_number'],
                'application_date' => $paymentData['application_date'],
                'payment_reference' => $paymentData['payment_reference'],
                'currency' => $paymentData['currency'],
                'payment_amount' => $paymentData['payment_amount'],
            ]);
        }

        CustomerPaymentLine::where('customer_payment_id', $customerPayment->id)->delete();

        if (!empty($paymentData['lines'])) {
            foreach ($paymentData['lines'] as $paymentLine) {
                CustomerPaymentLine::create([
                    'customer_payment_id' => $customerPayment->id,
                    'document_type' => $paymentLine['document_type'],
                    'reference_number' => $paymentLine['reference_number'],
                    'amount_paid' => $paymentLine['amount_paid'],
                    'date' => $paymentLine['date'],
                    'due_date' => $paymentLine['due_date'],
                    'balance' => $paymentLine['balance'],
                    'currency' => $paymentLine['currency'],
                ]);
            }
        }
    }
}
