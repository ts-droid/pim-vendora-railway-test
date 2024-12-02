<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Models\CustomerInvoice;
use Illuminate\Http\Request;

class VismaNetCustomerInvoiceService extends VismaNetApiService
{
    public function fetchCustomerInvoices(string $updatedAfter = '')
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_customer_invoices_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = $updatedAfter;
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $invoices = $this->getPagedResult('/v1/customerinvoice', $params);

        if ($invoices) {
            foreach ($invoices as $invoice) {
                if (!$invoice || !is_array($invoice)) {
                    continue;
                }

                $fetchedData = true;

                $this->importCustomerInvoice($invoice);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_customer_invoices_fetch' => $fetchTime]);
        }
    }

    public function importCustomerInvoice(array $invoice)
    {
        $orderNumbers = [];

        if ($invoice['hold'] ?? false) {
            return $orderNumbers;
        }

        $invoiceController = new CustomerInvoiceController();

        $invoiceData = [
            'invoice_number' => (string) ($invoice['referenceNumber'] ?? ''),
            'date' => date('Y-m-d', strtotime($invoice['documentDate'] ?? '')),
            'due_date' => date('Y-m-d', strtotime($invoice['documentDueDate'] ?? '')),
            'status' => (string) ($invoice['status'] ?? ''),
            'customer_number' => (string) ($invoice['customer']['number'] ?? ''),
            'credit_terms' => (string) ($invoice['creditTerms']['description'] ?? ''),
            'currency' => (string) ($invoice['currencyId'] ?? ''),
            'amount' => (float) ($invoice['amount'] ?? 0),
            'paid_at' => null,
            'lines' => []
        ];

        // Calculate paid at date
        $amountPaid = 0;
        $payDate = '';

        $applications = $invoice['applications'] ?? null;
        if ($applications) {
            foreach ($applications as $application) {
                $docType = $application['docType'] ?? '';
                $applicationDate = date('Y-m-d', strtotime($application['applicationDate']));
                $applicationAmount = (float) ($application['amountPaid'] ?? 0);

                if ($docType !== 'PMT') {
                    continue;
                }

                $amountPaid += $applicationAmount;
                $amountPaid = round($amountPaid, 2);

                if (!$payDate || $payDate < $applicationDate) {
                    $payDate = $applicationDate;
                }

                if ($amountPaid >= $invoice['amountInCurrency']) {
                    $invoiceData['paid_at'] = $payDate;
                    break;
                }
            }
        }

        // Add invoice lines
        foreach (($invoice['invoiceLines'] ?? []) as $invoiceLine) {
            $salesOrderNumber = (string) ($invoiceLine['soOrderNbr'] ?? '');
            $orderNumbers[] = $salesOrderNumber;

            $invoiceData['lines'][] = [
                'line_key' => (string) ($invoiceLine['lineNumber'] ?? ''),
                'article_number' => (string) ($invoiceLine['inventoryNumber'] ?? ''),
                'description' => (string) ($invoiceLine['description'] ?? ''),
                'order_number' => $salesOrderNumber,
                'shipment_number' => (string) ($invoiceLine['soShipmentNbr'] ?? ''),
                'line_type' => (string) ($invoiceLine['lineType'] ?? ''),
                'quantity' => (int) ($invoiceLine['quantity'] ?? 0),
                'unit_price' => (float) ($invoiceLine['unitPrice'] ?? 0),
                'amount' => (float) ($invoiceLine['amount'] ?? 0),
                'cost' => (float) ($invoiceLine['cost'] ?? 0),
                'sales_person_id' => (string) ($invoiceLine['salesperson'] ?? ''),
            ];
        }

        $existingInvoice = CustomerInvoice::where('invoice_number', $invoiceData['invoice_number'])->first();

        if (!$existingInvoice) {
            // Create new order
            $invoiceController->store(new Request($invoiceData));
        }
        else {
            // Update existing order
            $invoiceController->update(new Request($invoiceData), $existingInvoice);
        }

        return $orderNumbers;
    }
}
