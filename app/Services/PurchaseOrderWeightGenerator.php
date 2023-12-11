<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderWeightGenerator
{
    private array $quantityPerMonth = [];

    public function generateMonthWeights()
    {
        $this->loadQuantityPerMonth();

        $average = array_sum($this->quantityPerMonth) / count($this->quantityPerMonth);

        if (!$average) {
            return;
        }

        $configs = [];
        for ($i = 1;$i <= 12;$i++) {
            $configs['purchase_system_weight_auto_' . $i] = $this->quantityPerMonth[$i];
        }

        ConfigController::setConfigs($configs);
    }

    /**
     * Load the quantity sold per month last year
     * @return void
     */
    public function loadQuantityPerMonth()
    {
        $year = date('Y', strtotime('-1 year'));

        if ($year <= 2022) {
            $year = 2023;
        }

        for ($i = 1;$i <= 12;$i++) {
            $month = $i;

            if ($month < 10) {
                $month = '0' . $month;
            }

            $startDate = $year . '-' . $month . '-01';
            $endDate = $year . '-' . $month . '-' . date('t', strtotime($startDate));

            $this->quantityPerMonth[$i] = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->whereBetween('sales_orders.date', [$startDate, $endDate])
                ->sum('sales_order_lines.quantity');
        }
    }
}
