<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class SalesDashboardReporter
{
    private array $customerNumbers;

    private array $monthSummary = [];
    private array $yearSummary = [];

    function __construct(
        private readonly int $salesPersonID
    )
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        // Load customers connected to the sales person
        $customers = DB::table('customers')
            ->select('customers.id', 'customers.external_id', 'customers.customer_number', 'customers.name', 'customers.country')
            ->join('sales_people', 'sales_people.external_id', '=', 'customers.sales_person_id')
            ->where('sales_people.id', '=', $this->salesPersonID)
            ->get()
            ->toArray();

        if (!$customers) {
            return;
        }

        $this->customerNumbers = array_map(function ($customer) {
            return $customer->customer_number;
        }, $customers);


        // Load sales data
        $this->monthSummary = [
            'current' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-1 month')), date('Y-m-d 23:59:59')),
            'last' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-13 month')), date('Y-m-d 23:59:59', strtotime('-12 month'))),
        ];

        $this->yearSummary = [
            'current' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-1 year')), date('Y-m-d 23:59:59')),
            'last' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-2 year')), date('Y-m-d 23:59:59', strtotime('-1 year'))),
        ];
    }

    public function getSummary(): array
    {
        $monthTurnoverChange = 'inf';
        if ($this->monthSummary['last']['turnover'] != 0) {
            $monthTurnoverChange = round($this->monthSummary['current']['turnover'] / $this->monthSummary['last']['turnover'], 1);
        }

        $monthMarginChange = 'inf';
        if ($this->monthSummary['last']['margin'] != 0) {
            $monthTurnoverChange = round($this->monthSummary['current']['margin'] / $this->monthSummary['last']['margin'], 1);
        }

        $monthProfitChange = 'inf';
        if ($this->monthSummary['last']['profit'] != 0) {
            $monthTurnoverChange = round($this->monthSummary['current']['profit'] / $this->monthSummary['last']['profit'], 1);
        }

        $yearTurnoverChange = 'inf';
        if ($this->yearSummary['last']['turnover'] != 0) {
            $monthTurnoverChange = round($this->yearSummary['current']['turnover'] / $this->yearSummary['last']['turnover'], 1);
        }

        $yearMarginChange = 'inf';
        if ($this->yearSummary['last']['margin'] != 0) {
            $monthTurnoverChange = round($this->yearSummary['current']['margin'] / $this->yearSummary['last']['margin'], 1);
        }

        $yearProfitChange = 'inf';
        if ($this->yearSummary['last']['profit'] != 0) {
            $monthTurnoverChange = round($this->yearSummary['current']['profit'] / $this->yearSummary['last']['profit'], 1);
        }

        return [
            'turnover' => [
                'month' => [
                    'amount' => $this->monthSummary['current']['turnover'],
                    'change' => $monthTurnoverChange,
                ],
                'year' => [
                    'amount' => $this->yearSummary['current']['turnover'],
                    'change' => $yearTurnoverChange,
                ],
            ],
            'margin' => [
                'month' => [
                    'amount' => $this->monthSummary['current']['margin'],
                    'change' => $monthMarginChange,
                ],
                'year' => [
                    'amount' => $this->yearSummary['current']['margin'],
                    'change' => $yearMarginChange,
                ],
            ],
            'profit' => [
                'month' => [
                    'amount' => $this->monthSummary['current']['profit'],
                    'change' => $monthProfitChange,
                ],
                'year' => [
                    'amount' => $this->yearSummary['current']['profit'],
                    'change' => $yearProfitChange,
                ],
            ],
        ];
    }

    public function getTopBrands(): array
    {
        $topBrands = [
            'brands' => [],
            'summary' => [
                'units' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'revenue' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ],
        ];

        for ($i = 0;$i < 10;$i++) {
            $topBrands['brands'][] = [
                'name' => 'Satechi',
                'units' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'revenue' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ];
        }

        return $topBrands;
    }

    public function getTopCustomers(): array
    {
        $topCustomers = [];

        for ($i = 0;$i < 10;$i++) {
            $topCustomers[] = [
                'name' => 'Elkjöp Nordic AS',
                'country' => 'NO',
                'amount' => 0,
                'change' => 0,
            ];
        }

        return $topCustomers;
    }

    public function getOrderPipeline(): array
    {
        $orderPipeline = [];

        for ($i = 0;$i < 3;$i++) {
            $orderPipeline[] = [
                'customer' => 'Play Distrubution',
                'value' => 0,
                'shipping_date' => date('Y-m-d'),
            ];
        }

        return $orderPipeline;
    }

    private function getSalesData(string $startDate, string $endDate): array
    {
        $sales = DB::table('sales_order_lines')
            ->select(
                DB::raw('SUM(sales_order_lines.unit_price * sales_order_lines.quantity * sales_orders.exchange_rate) as total_price'),
                DB::raw('SUM(sales_order_lines.unit_cost * sales_order_lines.quantity * sales_orders.exchange_rate) as total_cost')
            )
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.date', '>=', $startDate)
            ->where('sales_orders.date', '<=', $endDate)
            ->whereIn('sales_orders.customer', $this->customerNumbers)
            ->first();

        $totalPrice = $sales->total_price ?? 0;
        $totalCost = $sales->total_cost ?? 0;

        $totalProfit = $totalPrice - $totalCost;

        $totalMargin = ($totalPrice != 0 ? $totalProfit / $totalPrice : 0) * 100;

        return [
            'turnover' => round($totalPrice),
            'cost' => round($totalCost),
            'profit' => round($totalProfit),
            'margin' => round($totalMargin, 1),
        ];
    }
}
