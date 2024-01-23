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
        $invoiceLines = $this->getInvoiceLines(date('Y-01-01'), date('Y-m-d'));
        $invoiceLinesLastYear = $this->getInvoiceLines(date('Y-01-01', strtotime('-1 year')), date('Y-m-d', strtotime('-1 year')));

        $topBrands = [];

        foreach ($invoiceLines as $invoiceLine) {
            if (!isset($topBrands[$invoiceLine->supplier_number])) {
                $topBrands[$invoiceLine->supplier_number] = [
                    'supplier_number' => $invoiceLine->supplier_number,
                    'name' => $invoiceLine->supplier_name,
                    'units' => 0,
                    'units_last_year' => 0,
                    'units_change' => 'inf',
                    'revenue' => 0,
                    'revenue_last_year' => 0,
                    'revenue_change' => 'inf'
                ];
            }

            $topBrands[$invoiceLine->supplier_number]['units'] += $invoiceLine->quantity;
            $topBrands[$invoiceLine->supplier_number]['revenue'] += $invoiceLine->amount;
        }

        foreach ($invoiceLinesLastYear as $invoiceLine) {
            if (!isset($topBrands[$invoiceLine->supplier_number])) {
                continue;
            }

            $topBrands[$invoiceLine->supplier_number]['units_last_year'] += $invoiceLine->quantity;
            $topBrands[$invoiceLine->supplier_number]['revenue_last_year'] += $invoiceLine->amount;
        }

        foreach ($topBrands as &$topBrand) {
            if ($topBrand['units_last_year'] != 0) {
                $topBrand['units_change'] = round((($topBrand['units'] / $topBrand['units_last_year']) - 1) * 100, 1);
            }

            if ($topBrand['revenue_last_year'] != 0) {
                $topBrand['revenue_change'] = round((($topBrand['revenue'] / $topBrand['revenue_last_year']) - 1) * 100, 1);
            }
        }

        // Sort brands based on revenue
        usort($topBrands, function ($item1, $item2) {
            return $item2['revenue'] <=> $item1['revenue'];
        });

        return array_values($topBrands);
    }

    public function getTopCustomers(): array
    {
        $invoiceLines = $this->getInvoiceLines(date('Y-01-01'), date('Y-m-d'));
        $invoiceLinesLastYear = $this->getInvoiceLines(date('Y-01-01', strtotime('-1 year')), date('Y-m-d', strtotime('-1 year')));

        $topCustomers = [];

        foreach ($invoiceLines as $invoiceLine) {
            if (!isset($topCustomers[$invoiceLine->customer_number])) {
                $topCustomers[$invoiceLine->customer_number] = [
                    'name' => $invoiceLine->customer_name,
                    'country' => $invoiceLine->customer_country,
                    'amount' => 0,
                    'amount_last_year' => 0,
                    'change' => 'inf',
                ];

                $topCustomers[$invoiceLine->customer_number]['amount'] += $invoiceLine->amount;
            }
        }

        foreach ($invoiceLinesLastYear as $invoiceLine) {
            if (!isset($topCustomers[$invoiceLine->customer_number])) {
                continue;
            }

            $topCustomers[$invoiceLine->customer_number]['amount_last_year'] += $invoiceLine->amount;
        }

        foreach ($topCustomers as &$customer) {
            if ($customer['amount_last_year'] != 0) {
                $customer['change'] = round((($customer['amount'] / $customer['amount_last_year']) - 1) * 100, 1);
            }
        }

        // Sort customers based on amount
        usort($topCustomers, function ($item1, $item2) {
            return $item2['amount'] <=> $item1['amount'];
        });

        return array_values($topCustomers);
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
            ->leftJoin('customers', 'customers.customer_number', '=', 'customer_invoices.customer_number')
            ->leftJoin('articles', 'articles.article_number', '=', 'customer_invoice_lines.article_number')
            ->leftJoin('suppliers', 'suppliers.number', '=', 'articles.supplier_number')
            ->select(
                'customer_invoices.customer_number',
                'customers.name AS customer_name',
                'customers.country AS customer_country',
                'suppliers.number AS supplier_number',
                'suppliers.name AS supplier_name',
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
