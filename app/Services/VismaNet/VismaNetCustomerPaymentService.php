<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use App\Services\Models\CustomerPaymentService;
use Illuminate\Http\Request;

class VismaNetCustomerPaymentService extends VismaNetApiService
{
    public function fetchCustomerPayments(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_customer_payments_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = $updatedAfter;
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $payments = $this->getPagedResult('/v1/customerPayment', $params);

        if ($payments) {
            foreach ($payments as $payment) {
                $fetchedData = true;

                if (!$payment || !is_array($payment)) {
                    continue;
                }

                $this->importPayment($payment);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_customer_payments_fetch' => $fetchTime]);
        }
    }

    private function importPayment(array $payment): void
    {
        $paymentData = [
            'reference_number' => $payment['refNbr'] ?? '',
            'status' => $payment['status'] ?? '',
            'customer_number' => ($payment['customer']['number'] ?? ''),
            'application_date' => date('Y-m-d', strtotime($payment['applicationDate'])),
            'payment_reference' => $payment['paymentRef'] ?? '',
            'currency' => $payment['currency'] ?? '',
            'payment_amount' => $payment['paymentAmount'] ?? 0,
            'lines' => [],
        ];

        if ($payment['paymentLines'] ?? null) {
            foreach ($payment['paymentLines'] as $paymentLine) {
                $paymentData['lines'][] = [
                    'document_type' => $paymentLine['documentType'] ?? '',
                    'reference_number' => $paymentLine['refNbr'] ?? '',
                    'amount_paid' => $paymentLine['amountPaid'] ?? 0,
                    'date' => $paymentLine['date'] ?? '',
                    'due_date' => $paymentLine['dueDate'] ?? '',
                    'balance' => $paymentLine['balance'] ?? 0,
                    'currency' => $paymentLine['currency'] ?? '',
                ];
            }
        }

        $customerPaymentService = new CustomerPaymentService();
        $customerPaymentService->addCustomerPayment($paymentData);
    }
}
