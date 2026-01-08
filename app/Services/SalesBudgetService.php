<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesBudgetService
{
    public function setBudget(int $salesPersonID, int $year, int $month, int $turnover): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $exists = DB::table('sales_person_budget')
            ->where('sales_person_id', $salesPersonID)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        if ($exists) {
            DB::table('sales_person_budget')
                ->where('sales_person_id', $salesPersonID)
                ->where('year', $year)
                ->where('month', $month)
                ->update([
                    'turnover' => $turnover
                ]);
        }
        else {
            DB::table('sales_person_budget')->insert([
                    'sales_person_id' => $salesPersonID,
                    'year' => $year,
                    'month' => $month,
                    'turnover' => $turnover
                ]);
        }
    }

    public function getBudgetForYear(array $salesPersonIDs, int $year)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $budget = [];

        $rows = DB::table('sales_person_budget')
            ->whereIn('sales_person_id', $salesPersonIDs)
            ->where('year', $year)
            ->get();

        for ($i = 1;$i <= 12;$i++) {
            $budget[$i] = [
                'turnover' => 0,
            ];

            foreach ($rows as $row) {
                if ($row->month != $i) {
                    continue;
                }

                $budget[$i]['turnover'] += $row->turnover;
            }
        }

        return $budget;
    }
}
