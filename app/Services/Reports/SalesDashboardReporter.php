<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class SalesDashboardReporter
{
    private array $customerNumbers;

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

        $this->customerNumbers = array_map(function ($customer) {
            return $customer->customer_number;
        }, $customers);
    }

    public function getSummary(): array
    {
        // Load sales data
        $monthSummary = [
            'current' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-1 month')), date('Y-m-d 23:59:59')),
            'last' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-13 month')), date('Y-m-d 23:59:59', strtotime('-12 month'))),
        ];

        $yearSummary = [
            'current' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-1 year')), date('Y-m-d 23:59:59')),
            'last' => $this->getSalesData(date('Y-m-d 00:00:00', strtotime('-2 year')), date('Y-m-d 23:59:59', strtotime('-1 year'))),
        ];

        $monthTurnoverChange = 'inf';
        if ($monthSummary['last']['turnover'] != 0) {
            $monthTurnoverChange = round((($monthSummary['current']['turnover'] / $monthSummary['last']['turnover']) - 1) * 100, 1);
        }

        $monthMarginChange = 'inf';
        if ($monthSummary['last']['margin'] != 0) {
            $monthMarginChange = round((($monthSummary['current']['margin'] / $monthSummary['last']['margin']) - 1) * 100, 1);
        }

        $monthProfitChange = round($monthSummary['current']['profit'] - $monthSummary['last']['profit']);

        $yearTurnoverChange = 'inf';
        if ($yearSummary['last']['turnover'] != 0) {
            $yearTurnoverChange = round((($yearSummary['current']['turnover'] / $yearSummary['last']['turnover']) - 1) * 100, 1);
        }

        $yearMarginChange = 'inf';
        if ($yearSummary['last']['margin'] != 0) {
            $yearMarginChange = round((($yearSummary['current']['margin'] / $yearSummary['last']['margin']) - 1) * 100, 1);
        }

        $yearProfitChange = round($yearSummary['current']['profit'] - $yearSummary['last']['profit']);

        return [
            'turnover' => [
                'month' => [
                    'amount' => $monthSummary['current']['turnover'],
                    'change' => $monthTurnoverChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['turnover'],
                    'change' => $yearTurnoverChange,
                ],
            ],
            'margin' => [
                'month' => [
                    'amount' => $monthSummary['current']['margin'],
                    'change' => $monthMarginChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['margin'],
                    'change' => $yearMarginChange,
                ],
            ],
            'profit' => [
                'month' => [
                    'amount' => $monthSummary['current']['profit'],
                    'change' => $monthProfitChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['profit'],
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
        $topCustomers = DB::table('sales_orders')
            ->join('customers', 'sales_orders.customer', '=', 'customers.customer_number')
            ->leftJoin(DB::raw('(
                SELECT
                    customer,
                    SUM(order_total * exchange_rate) AS amount_last_year
                FROM sales_orders
                WHERE
                    date >= "' . date('Y-01-01 00:00:00', strtotime('-1 year')) . '" AND
                    date <= "' . date('Y-m-d H:i:s', strtotime('-1 year')) . '"
                GROUP BY customer
            ) AS previous_year_revenue'), function($join) {
                $join->on('sales_orders.customer', '=', 'previous_year_revenue.customer');
            })
            ->select(
                'customers.name',
                'customers.country',
                DB::raw('SUM(sales_orders.order_total * sales_orders.exchange_rate) AS amount'),
                'previous_year_revenue.amount_last_year'
            )
            ->where('sales_orders.date', '>=', date('Y-01-01 00:00:00'))
            ->where('sales_orders.date', '<=', date('Y-m-d H:i:s'))
            ->whereIn('sales_orders.customer', $this->customerNumbers)
            ->groupBy('sales_orders.customer', 'customers.name', 'customers.country')
            ->orderBy('amount', 'DESC')
            ->get()
            ->toArray();

        if ($topCustomers) {
            foreach ($topCustomers as &$customer) {
                $customer->change = 'inf';

                if ($customer->amount_last_year != 0) {
                    $customer->change = round((($customer->amount / $customer->amount_last_year) - 1) * 100, 1);
                }
            }
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
