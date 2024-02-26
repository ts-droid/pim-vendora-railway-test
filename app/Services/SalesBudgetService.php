<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesBudgetService
{
    public function setBudget(int $salesPersonID, int $year, int $month, int $turnover): void
    {
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
