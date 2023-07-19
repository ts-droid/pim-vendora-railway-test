<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class SalesDataController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        // TODO: Filter results with these parameters
        $customerNumbers = explode(',', $request->get('customer_numbers', ''));
        $brands = explode(',', $request->get('brand', ''));

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

        $response = $invoiceController->get(new Request([
            'date' => $startDate . ',' . $endDate,
        ]));

        $invoices = ApiResponseController::getDataFromResponse($response);

        if ($invoices) {
            foreach ($invoices as $invoice) {
                foreach ($invoice['lines'] as $line) {
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
