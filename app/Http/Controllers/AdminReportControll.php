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

        // Flatten all account numbers from the plan
        $allAccounts = collect($accountPlan)
            ->flatMap(fn ($g) => $g['accounts'] ?? [])
            ->unique()
            ->values()
            ->all();

        // One query: balances + descriptions
        $rows = DB::table('ledger_account_transactions as t')
            ->join('ledger_account as a', 'a.number', '=', 't.account_number')
            ->whereBetween('t.date', [$startDate, $endDate])
            ->whereIn('t.account_number', $allAccounts)
            ->groupBy('t.account_number', 'a.description')
            ->selectRaw('t.account_number, a.description, SUM(t.debit - t.credit) as balance')
            ->get();

        $byAccount = $rows->keyBy('account_number');

        $report = [];
        foreach ($accountPlan as $accountGroup) {
            $accounts = [];
            $totalBalance = 0.0;

            foreach (($accountGroup['accounts'] ?? []) as $accountNumber) {
                $row = $byAccount->get($accountNumber);

                $balance = (float) ($row->balance ?? 0);
                $accounts[] = [
                    'number' => $accountNumber,
                    'description' => $row->description ?? '',
                    'balance' => $balance
                ];
                $totalBalance += $balance;
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
