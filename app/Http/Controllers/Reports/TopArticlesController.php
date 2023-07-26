<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class TopArticlesController extends Controller
{
    public function index(Request $request)
    {
        // Required parameters
        $startDate = array_filter(explode(',', $request->get('start_date', '')));
        $endDate = array_filter(explode(',', $request->get('end_date', '')));
        $numPeriods = min(count($startDate), count($endDate));

        // Optional parameters
        $suppliers = explode(',', $request->get('suppliers', ''));

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }

        $invoiceController = new CustomerInvoiceController();

        $results = [];

        for ($i = 0;$i < $numPeriods;$i++) {
            $response = $invoiceController->get(new Request([
                'date' => $startDate[$i] . ',' . $endDate[$i],
                'page_size' => 0
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

                        if (!isset($subResults[$line['article_number']])) {
                            $subResults[$line['article_number']] = [
                                'article_number' => $line['article_number'],
                                'description' => $line['description'],
                                'quantity' => 0,
                                'amount' => 0,
                                'cost' => 0,
                            ];
                        }

                        $subResults[$line['article_number']]['quantity'] += $line['quantity'];
                        $subResults[$line['article_number']]['amount'] += $line['amount'];
                        $subResults[$line['article_number']]['cost'] += $line['cost'];
                    }
                }
            }

            // Sort results by amount
            usort($subResults, function($a, $b) {
                return $b['amount'] - $a['amount'];
            });

            // Remove article number as array key
            $newResults = [];
            foreach ($subResults as $result) {
                $newResults[] = $result;
            }
            $subResults = $newResults;

            $results[] = $subResults;
        }

        if ($numPeriods == 1) {
            $results = $results[0];
        }

        return ApiResponseController::success($results);
    }
}
