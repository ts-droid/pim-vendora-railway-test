<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $totalBalance = 0;

            foreach ($accountGroup['accounts'] as $accountNumber) {
                $accountDescription = DB::table('ledger_account')
                    ->where('number', $accountNumber)
                    ->value('description');

                $accountBalance = (float) DB::table('ledger_account_transactions')
                    ->where('account_number', $accountNumber)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->selectRaw('SUM(debit - credit) AS balance')
                    ->value('balance');

                $accounts[] = [
                    'number' => $accountNumber,
                    'description' => $accountDescription,
                    'balance' => $accountBalance
                ];

                $totalBalance += $accountBalance;
            }

            $report[] = [
                'name' => $accountGroup['name'] ?? '',
                'accounts' => $accounts,
                'balance' => $totalBalance,
            ];
        }

        return ApiResponseController::success($report);
    }
}
