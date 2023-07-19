<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class TopCustomersController extends Controller
{
    public function index(Request $request)
    {
        // Required parameters
        $startDate = array_filter(explode(',', $request->get('start_date', '')));
        $endDate = array_filter(explode(',', $request->get('end_date', '')));
        $numPeriods = min(count($startDate), count($endDate));

        // Optional parameters
        $suppliers = explode(',', $request->get('suppliers', ''));
        $salesPersonIDs = explode(',', $request->get('sales_person_ids', ''));

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }

        $invoiceController = new CustomerInvoiceController();

        $results = [];

        for ($i = 0;$i < $numPeriods;$i++) {
            $response = $invoiceController->get(new Request([
                'date' => $startDate[$i] . ',' . $endDate[$i]
            ]));

            $invoices = ApiResponseController::getDataFromResponse($response);

            $subResults = [];

            if ($invoices) {
                foreach ($invoices as $invoice) {
                    foreach ($invoice['lines'] as $line) {
                        // Filter by supplier
                        if ($suppliers && !in_array(($line['article']['supplier']['name'] ?? ''), $suppliers)) {
                            continue;
                        }

                        // Filter by sales person
                        if ($salesPersonIDs && !in_array($line['sales_person_id'], $salesPersonIDs)) {
                            continue;
                        }

                        if (!isset($subResults[$invoice['customer_number']])) {
                            $subResults[$invoice['customer_number']] = [
                                'customer_number' => $invoice['customer_number'],
                                'customer' => $line['description'],
                                'amount' => 0,
                                'cost' => 0,
                                'quantity' => 0,
                                'margin' => 0,
                            ];
                        }

                        $subResults[$invoice['customer_number']]['amount'] += $line['amount'];
                        $subResults[$invoice['customer_number']]['cost'] += $line['cost'];
                        $subResults[$invoice['customer_number']]['quantity'] += $line['quantity'];
                    }
                }

                // Calculate margin
                foreach ($subResults as &$subResult) {
                    if ($subResult['amount']) {
                        $subResult['margin'] = round((($subResult['amount'] - $subResult['cost']) / $subResult['amount']) * 100, 2);
                    }
                }
            }

            // Sort results by revenue
            usort($subResults, function ($a, $b) {
                return $b['amount'] - $a['amount'];
            });

            $results[] = $subResults;
        }

        if ($numPeriods == 1) {
            $results = $results[0];
        }

        return ApiResponseController::success($results);
    }
}
