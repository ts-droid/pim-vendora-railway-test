<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class SalesDataController extends Controller
{
    public function index(Request $request)
    {
        // Required parameters
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        // Optional parameters
        $customerVATNumbers = explode(',', $request->get('customer_vat_numbers', ''));
        $suppliers = explode(',', $request->get('suppliers', ''));
        $salesPersonIDs = explode(',', $request->get('sales_person_ids', ''));

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }

        $result = [
            'revenue' => 0,
            'cost' => 0,
            'num_products' => 0,
            'gross_margin' => 0,
        ];

        $invoiceController = new CustomerInvoiceController();
        $customerController = new CustomerController();

        $response = $invoiceController->get(new Request([
            'date' => $startDate . ',' . $endDate,
            'customer_number' => $customerController->VATNumberToCustomerNumber($customerVATNumbers),
        ]));

        $invoices = ApiResponseController::getDataFromResponse($response);

        if ($invoices) {
            foreach ($invoices as $invoice) {
                foreach ($invoice['lines'] as $line) {
                    // Filter by supplier
                    if ($suppliers && !in_array($line['article']['supplier']['name'], $suppliers)) {
                        continue;
                    }

                    // Filter by sales person
                    if ($salesPersonIDs && !in_array($line['sales_person_id'], $salesPersonIDs)) {
                        continue;
                    }

                    $result['revenue'] += $line['amount'];
                    $result['cost'] += $line['cost'];
                    $result['num_products'] += $line['quantity'];
                }
            }
        }

        // Calculate gross margin
        if ($result['revenue']) {
            $result['gross_margin'] = (($result['revenue'] - $result['cost']) / $result['revenue']) * 100;
        }

        return ApiResponseController::success($result);
    }
}
