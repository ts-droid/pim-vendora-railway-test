<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminReportControll extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            return ApiResponseController::error('Start date and end date are required.');
        }


        $accountPlan = ConfigController::getConfig('admin_report_account_plan', '[]');
        $accountPlan = json_decode($accountPlan, true);

        $report = [];
        foreach ($accountPlan as $accountGroup) {
            $accounts = [];
            foreach ($accountGroup['accounts'] as $accountNumber) {
                $accounts[] = [
                    'number' => $accountNumber,
                    'description' => 'tba',
                    'balance' => 0
                ];
            }

            $report[] = [
                'name' => $accountGroup['name'] ?? '',
                'accounts' => $accounts,
            ];
        }

        return ApiResponseController::success($report);
    }
}
