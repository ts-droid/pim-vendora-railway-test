<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class ArticleSalesController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        // TODO: Filter results with these parameters
        $customerNumbers = explode(',', $request->get('customer_numbers', ''));
        $articleNumber = $request->get('article_number', '');

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }

        $results = [];

        $invoiceController = new CustomerInvoiceController();

        $response = $invoiceController->get(new Request([
            'date' => $startDate . ',' . $endDate,
        ]));

        $invoices = ApiResponseController::getDataFromResponse($response);

        if ($invoices) {
            foreach ($invoices as $invoice) {
                foreach ($invoice['lines'] as $line) {
                    if (!isset($results[$line['article_number']])) {
                        $results[$line['article_number']] = [
                            'article_number' => $line['article_number'],
                            'description' => $line['description'],
                            'supplier' => ($line['article']['supplier']['name'] ?? ''),
                            'quantity' => 0,
                            'cost' => 0,
                            'amount' => 0,
                            'avg_price' => 0,
                        ];
                    }

                    $results[$line['article_number']]['cost'] += $line['cost'];
                    $results[$line['article_number']]['quantity'] += $line['quantity'];
                    $results[$line['article_number']]['amount'] += $line['amount'];
                }
            }

            // Calculate average price
            foreach ($results as $key => $result) {
                if ($result['quantity']) {
                    $results[$key]['avg_price'] = $result['amount'] / $result['quantity'];
                }
            }

            // Remove article number as array key
            $newResults = [];
            foreach ($results as $result) {
                $newResults[] = $result;
            }
            $result = $newResults;
        }

        return ApiResponseController::success($results);
    }
}
