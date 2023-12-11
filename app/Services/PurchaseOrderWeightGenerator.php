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

        $configs = [];
        for ($i = 1;$i <= 12;$i++) {
            $configs['purchase_system_weight_auto_' . $i] = round($this->quantityPerMonth[$i] / $average, 2);
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

        for ($i = 1;$i <= 12;$i++) {
            $startDate = $year . '-' . $i . '-01';
            $endDate = $year . '-' . $i . '-' . date('t', strtotime($startDate));

            $this->quantityPerMonth[$i] = (int) DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->whereBetween('sales_orders.date', [$startDate, $endDate])
                ->sum('sales_order_lines.quantity');
        }
    }
}
