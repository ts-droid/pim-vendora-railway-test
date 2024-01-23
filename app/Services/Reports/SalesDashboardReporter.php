<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class SalesDashboardReporter
{
    private array $customerNumbers;

    private array $invoiceLines;

    function __construct(
        private readonly int $salesPersonID
    )
    {
        $this->loadData();
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
        $topBrands = DB::table('sales_order_lines')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->join('suppliers', 'suppliers.number', '=', 'articles.supplier_number')
            ->select(
                'articles.supplier_number',
                'suppliers.name',
                DB::raw('SUM(sales_order_lines.quantity) AS units'),
                DB::raw('SUM(sales_order_lines.unit_price * sales_order_lines.quantity * sales_orders.exchange_rate) AS revenue'),
            )
            ->where('sales_orders.date', '>=', date('Y-01-01 00:00:00'))
            ->where('sales_orders.date', '<=', date('Y-m-d H:i:s'))
            ->whereIn('sales_orders.customer', $this->customerNumbers)
            ->groupBy('articles.supplier_number', 'suppliers.name')
            ->get()
            ->toArray();

        if ($topBrands) {
            foreach($topBrands as &$brand) {
                $lastYear = DB::table('sales_order_lines')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                    ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
                    ->select(
                        DB::raw('SUM(sales_order_lines.quantity) AS units'),
                        DB::raw('SUM(sales_order_lines.unit_price * sales_order_lines.quantity * sales_orders.exchange_rate) AS revenue'),
                    )
                    ->whereIn('sales_orders.customer', $this->customerNumbers)
                    ->where('articles.supplier_number', '=', $brand->supplier_number)
                    ->where('sales_orders.date', '>=', date('Y-01-01 00:00:00', strtotime('-1 year')))
                    ->where('sales_orders.date', '<=', date('Y-m-d H:i:s', strtotime('-1 year')))
                    ->first();

                $brand->units_last_year = $lastYear->units;
                $brand->revenue_last_year = $lastYear->revenue;

                $brand->units_change = 'inf';
                if ($brand->units_last_year != 0) {
                    $brand->units_change = round((($brand->units / $brand->units_last_year) - 1) * 100, 1);
                }

                $brand->revenue_change = 'inf';
                if ($brand->revenue_last_year != 0) {
                    $brand->revenue_change = round((($brand->revenue / $brand->revenue_last_year) - 1) * 100, 1);
                }
            }
        }

        $units = 0;
        $unitsLastYear = 0;

        $revenue = 0;
        $revenueLastYear = 0;

        foreach ($topBrands as $brand) {
            $units += $brand->units;
            $unitsLastYear += $brand->units_last_year;

            $revenue += $brand->revenue;
            $revenueLastYear += $brand->revenue_last_year;
        }

        $unitsChange = 'inf';
        if ($unitsLastYear != 0) {
            $unitsChange = round((($units / $unitsLastYear) - 1) * 100, 1);
        }

        $revenueChange = 'inf';
        if ($revenueLastYear != 0) {
            $revenueChange = round((($revenue / $revenueLastYear) - 1) * 100, 1);
        }

        return [
            'brands' => $topBrands,
            'summary' => [
                'units' => [
                    'amount' => $units,
                    'change' => $unitsChange,
                ],
                'revenue' => [
                    'amount' => $revenue,
                    'change' => $revenueChange,
                ],
            ],
        ];
    }

    public function getTopCustomers(): array
    {
        $currentModesResult = DB::selectOne("SELECT @@sql_mode as sql_mode");
        $currentModes = $currentModesResult->sql_mode;

        $newModes = collect(explode(',', $currentModes))->reject(function ($value) {
            return $value === 'ONLY_FULL_GROUP_BY';
        })->implode(',');

        DB::statement("SET SESSION sql_mode='{$newModes}'");

        $topCustomers = DB::table('customer_invoices')
            ->join('customers', 'customer_invoices.customer_number', '=', 'customers.customer_number')
            ->leftJoin(DB::raw('(
                SELECT
                    customer_number,
                    SUM(amount) AS amount_last_year
                FROM customer_invoices
                WHERE
                    date >= "' . date('Y-01-01 00:00:00', strtotime('-1 year')) . '" AND
                    date <= "' . date('Y-m-d H:i:s', strtotime('-1 year')) . '"
                GROUP BY customer_number
            ) AS previous_year_revenue'), function($join) {
                $join->on('customer_invoices.customer_number', '=', 'previous_year_revenue.customer_number');
            })
            ->select(
                'customers.name',
                'customers.country',
                DB::raw('SUM(customer_invoices.amount) AS amount'),
                'previous_year_revenue.amount_last_year'
            )
            ->where('customer_invoices.date', '>=', date('Y-01-01 00:00:00'))
            ->where('customer_invoices.date', '<=', date('Y-m-d H:i:s'))
            ->whereIn('customer_invoices.customer_number', $this->customerNumbers)
            ->groupBy('customer_invoices.customer_number')
            ->orderBy('amount', 'DESC')
            ->get()
            ->toArray();

        DB::statement("SET SESSION sql_mode='{$currentModes}'");

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
        $invoiceLines = $this->getInvoiceLines($startDate, $endDate);

        $totalPrice = 0;
        $totalCost = 0;

        foreach ($invoiceLines as $invoiceLine) {
            $totalPrice += $invoiceLine->amount;
            $totalCost += $invoiceLine->cost;
        }

        $totalProfit = $totalPrice - $totalCost;
        $totalMargin = ($totalPrice != 0 ? $totalProfit / $totalPrice : 0) * 100;

        return [
            'turnover' => round($totalPrice),
            'cost' => round($totalCost),
            'profit' => round($totalProfit),
            'margin' => round($totalMargin, 1),
        ];
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

        // Load all invoice lines
        $startDate = date('Y-m-d', strtotime('-2 year'));
        $endDate = date('Y-m-d');

        $this->invoiceLines = DB::table('customer_invoice_lines')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->select(
                'customer_invoice_lines.article_number',
                'customer_invoice_lines.quantity',
                'customer_invoice_lines.unit_price',
                'customer_invoice_lines.amount',
                'customer_invoice_lines.cost',
                'customer_invoices.date'
            )
            ->whereIn('customer_invoices.customer_number', $this->customerNumbers)
            ->where('customer_invoices.date', '>=', $startDate)
            ->where('customer_invoices.date', '<=', $endDate)
            ->get()
            ->toArray();
    }

    private function getInvoiceLines(string $startDate, string $endDate): array
    {
        // Return all invoice lines between the given dates
        return array_filter($this->invoiceLines, function ($invoiceLine) use ($startDate, $endDate) {
            return $invoiceLine->date >= $startDate && $invoiceLine->date <= $endDate;
        });
    }
}
