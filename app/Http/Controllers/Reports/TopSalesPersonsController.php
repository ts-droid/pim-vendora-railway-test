<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerInvoiceController;
use Illuminate\Http\Request;

class TopSalesPersonsController extends Controller
{
    public function index(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        // Required parameters
        $startDate = array_filter(explode(',', $request->get('start_date', '')));
        $endDate = array_filter(explode(',', $request->get('end_date', '')));
        $numPeriods = min(count($startDate), count($endDate));

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
                        if (!$line['sales_person_id']) {
                            continue;
                        }

                        if (!isset($subResults[$line['sales_person_id']])) {
                            $subResults[$line['sales_person_id']] = [
                                'sales_person_id' => $line['sales_person_id'],
                                'sales_person' => ($line['sales_person']['name'] ?? ''),
                                'amount' => 0,
                                'cost' => 0,
                                'quantity' => 0,
                                'margin' => 0,
                            ];
                        }

                        $subResults[$line['sales_person_id']]['amount'] += $line['amount'];
                        $subResults[$line['sales_person_id']]['cost'] += $line['cost'];
                        $subResults[$line['sales_person_id']]['quantity'] += $line['quantity'];
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
