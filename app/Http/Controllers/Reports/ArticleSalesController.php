<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Models\Customer;
use Illuminate\Http\Request;

class ArticleSalesController extends Controller
{
    public function index(Request $request)
    {
        // Required parameters
        $startDate = array_filter(explode(',', $request->get('start_date', '')));
        $endDate = array_filter(explode(',', $request->get('end_date', '')));
        $numPeriods = min(count($startDate), count($endDate));

        // Optional parameters
        $customerVATNumbers = explode(',', $request->get('customer_vat_numbers', ''));
        $suppliers = explode(',', $request->get('suppliers', ''));
        $salesPersonIDs = explode(',', $request->get('sales_person_ids', ''));
        $articleNumber = $request->get('article_number', '');

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }

        $invoiceController = new CustomerInvoiceController();
        $customerController = new CustomerController();

        $results = [];

        for ($i = 0;$i < $numPeriods;$i++) {
            $response = $invoiceController->get(new Request([
                'date' => $startDate[$i] . ',' . $endDate[$i],
                'customer_number' => $customerController->VATNumberToCustomerNumber($customerVATNumbers),
                'page_size' => 0
            ]));

            $invoices = ApiResponseController::getDataFromResponse($response);

            $subResults = [];

            if ($invoices) {
                foreach ($invoices as $invoice) {
                    foreach ($invoice['lines'] as $line) {
                        // Filter by article number
                        if ($articleNumber && $line['article_numer'] != $articleNumber) {
                            continue;
                        }

                        // Filter by supplier
                        if ($suppliers && !in_array(($line['article']['supplier']['name'] ?? ''), $suppliers)) {
                            continue;
                        }

                        // Filter by sales person
                        if ($salesPersonIDs && !in_array($line['sales_person_id'], $salesPersonIDs)) {
                            continue;
                        }

                        if (!isset($subResults[$line['article_number']])) {
                            $subResults[$line['article_number']] = [
                                'article_number' => $line['article_number'],
                                'description' => $line['description'],
                                'supplier' => ($line['article']['supplier']['name'] ?? ''),
                                'quantity' => 0,
                                'cost' => 0,
                                'amount' => 0,
                                'avg_price' => 0,
                            ];
                        }

                        $subResults[$line['article_number']]['cost'] += $line['cost'];
                        $subResults[$line['article_number']]['quantity'] += $line['quantity'];
                        $subResults[$line['article_number']]['amount'] += $line['amount'];
                    }
                }

                // Calculate average price
                foreach ($subResults as $key => $result) {
                    if ($result['quantity']) {
                        $subResults[$key]['avg_price'] = $result['amount'] / $result['quantity'];
                    }
                }

                // Remove article number as array key
                $newResults = [];
                foreach ($subResults as $result) {
                    $newResults[] = $result;
                }
                $subResults = $newResults;
            }

            $results[] = $subResults;
        }

        if ($numPeriods == 1) {
            $results = $results[0];
        }

        return ApiResponseController::success($results);
    }
}
