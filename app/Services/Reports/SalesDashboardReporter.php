<?php

namespace App\Services\Reports;

use App\Models\SalesPerson;
use App\Services\CustomerCreditService;
use App\Services\TransactionService;
use App\Services\WGR\WGROrderQueueService;
use Illuminate\Support\Facades\DB;

class SalesDashboardReporter
{
    const EXCLUDE_SALES_PERSONS = [
        10 // Thomas Söderberg
    ];

    private bool $excludeShipping = false;

    private array $customerNumbers;

    private array $invoiceLines;

    function __construct(
        private readonly mixed $salesPersonIDs,
        private readonly string $customerNumber,
        private readonly string $supplierNumber,
        private readonly array $period,
        private readonly bool $addShipping = false
    )
    {
        if (!$this->addShipping || $this->salesPersonIDs || $this->customerNumber || $this->supplierNumber) {
            $this->excludeShipping = true;
        }

        $this->loadData();
    }

    public function getCharts(): array
    {
        $turnoverChart = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'],
            'datasets' => [
                'last_year' => [],
                'current_year' => []
            ],
        ];

        $marginChart = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'],
            'datasets' => [
                'last_year' => [],
                'current_year' => []
            ],
        ];

        for ($i = 1;$i <= 12;$i++) {
            $month = $i;
            if ($month < 10) {
                $month = '0' . $month;
            }

            $daysInMonth = date('t', strtotime(date('Y-' . $month . '-01')));

            $lastSalesData = $this->getSalesData(
                date('Y-' . $month . '-01 00:00:00', strtotime('-1 year')),
                date('Y-' . $month . '-' . $daysInMonth . ' 23:59:59', strtotime('-1 year'))
            );

            $currentSalesData = $this->getSalesData(
                date('Y-' . $month . '-01 00:00:00'),
                date('Y-' . $month . '-' . $daysInMonth . ' 23:59:59')
            );

            $turnoverChart['datasets']['last_year'][] = $lastSalesData['turnover'];
            $turnoverChart['datasets']['current_year'][] = $currentSalesData['turnover'];

            $marginChart['datasets']['last_year'][] = $lastSalesData['margin'];
            $marginChart['datasets']['current_year'][] = $currentSalesData['margin'];
        }

        return [
            'turnover' => $turnoverChart,
            'margin' => $marginChart
        ];
    }

    public function getSummary(): array
    {
        // Load sales data
        $periodSummary = [
            'current' => $this->getSalesData(
                date('Y-m-01 00:00:00', strtotime($this->period[0])),
                date('Y-m-d 23:59:59', strtotime($this->period[1]))
            ),
            'last' => $this->getSalesData(
                date('Y-m-01 00:00:00', strtotime('-1 year', strtotime($this->period[0]))),
                date('Y-m-d 23:59:59', strtotime('-1 year', strtotime($this->period[1])))
            ),
        ];

        $yearToDateSummary = [
            'current' => $this->getSalesData(
                date('Y-01-01 00:00:00'),
                date('Y-m-d 23:59:59'),
            ),
            'last' => $this->getSalesData(
                date('Y-01-01 00:00:00', strtotime('-1 year')),
                date('Y-m-d 23:59:59', strtotime('-1 year')),
            )
        ];

        $lastYearToDateSummary = [
            'current' => $this->getSalesData(
                date('Y-01-01 00:00:00', strtotime('-1 year')),
                date('Y-m-d 23:59:59', strtotime('-1 year')),
            ),
            'last' => $this->getSalesData(
                date('Y-01-01 00:00:00', strtotime('-2 year')),
                date('Y-m-d 23:59:59', strtotime('-2 year')),
            ),
        ];

        $yearSummary = [
            'current' => $this->getSalesData(
                date('Y-01-01 00:00:00', strtotime('-1 year')),
                date('Y-12-31 23:59:59', strtotime('-1 year'))
            ),
            'last' => $this->getSalesData(
                date('Y-01-01 00:00:00', strtotime('-2 year')),
                date('Y-12-31 23:59:59', strtotime('-2 year'))
            ),
        ];


        $monthTurnoverChange = 'inf';
        if ($periodSummary['last']['turnover'] != 0) {
            $monthTurnoverChange = round((($periodSummary['current']['turnover'] / $periodSummary['last']['turnover']) - 1) * 100, 1);
        }

        $monthMarginChange = round($periodSummary['current']['margin'] - $periodSummary['last']['margin'], 1);
        $monthProfitChange = round($periodSummary['current']['profit'] - $periodSummary['last']['profit']);


        $yearToDateTurnoverChange = 'inf';
        if ($yearToDateSummary['last']['turnover'] != 0) {
            $yearToDateTurnoverChange = round((($yearToDateSummary['current']['turnover'] / $yearToDateSummary['last']['turnover']) - 1) * 100, 1);
        }

        $yearToDateMarginChange = round($yearToDateSummary['current']['margin'] - $yearToDateSummary['last']['margin'], 1);
        $yearToDateProfitChange = round($yearToDateSummary['current']['profit'] - $yearToDateSummary['last']['profit']);


        $lastYearToDateTurnoverChange = 'inf';
        if ($lastYearToDateSummary['last']['turnover'] != 0) {
            $lastYearToDateTurnoverChange = round((($lastYearToDateSummary['current']['turnover'] / $lastYearToDateSummary['last']['turnover']) - 1) * 100, 1);
        }

        $lastYearToDateMarginChange = round($lastYearToDateSummary['current']['margin'] - $lastYearToDateSummary['last']['margin'], 1);
        $lastYearToDateProfitChange = round($lastYearToDateSummary['current']['profit'] - $lastYearToDateSummary['last']['profit']);


        $yearTurnoverChange = 'inf';
        if ($yearSummary['last']['turnover'] != 0) {
            $yearTurnoverChange = round((($yearSummary['current']['turnover'] / $yearSummary['last']['turnover']) - 1) * 100, 1);
        }

        $yearMarginChange = round($yearSummary['current']['margin'] - $yearSummary['last']['margin'], 1);
        $yearProfitChange = round($yearSummary['current']['profit'] - $yearSummary['last']['profit']);

        return [
            'turnover' => [
                'month' => [
                    'amount' => $periodSummary['current']['turnover'],
                    'amount_last' => $periodSummary['last']['turnover'],
                    'amount_shipping' => $periodSummary['current']['turnover_shipping'],
                    'change' => $monthTurnoverChange,
                ],
                'year_to_date' => [
                    'amount' => $yearToDateSummary['current']['turnover'],
                    'amount_shipping' => $yearToDateSummary['current']['turnover_shipping'],
                    'change' => $yearToDateTurnoverChange,
                ],
                'last_year_to_date' => [
                    'amount' => $lastYearToDateSummary['current']['turnover'],
                    'amount_shipping' => $lastYearToDateSummary['current']['turnover_shipping'],
                    'change' => $lastYearToDateTurnoverChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['turnover'],
                    'amount_shipping' => $yearSummary['current']['turnover_shipping'],
                    'change' => $yearTurnoverChange,
                ],
            ],
            'margin' => [
                'month' => [
                    'amount' => $periodSummary['current']['margin'],
                    'amount_last' => $periodSummary['last']['margin'],
                    'amount_shipping' => $periodSummary['current']['margin_shipping'],
                    'change' => $monthMarginChange,
                ],
                'year_to_date' => [
                    'amount' => $yearToDateSummary['current']['margin'],
                    'amount_shipping' => $yearToDateSummary['current']['margin_shipping'],
                    'change' => $yearToDateMarginChange,
                ],
                'last_year_to_date' => [
                    'amount' => $lastYearToDateSummary['current']['margin'],
                    'amount_shipping' => $lastYearToDateSummary['current']['margin_shipping'],
                    'change' => $lastYearToDateMarginChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['margin'],
                    'amount_shipping' => $yearSummary['current']['margin_shipping'],
                    'change' => $yearMarginChange,
                ],
            ],
            'profit' => [
                'month' => [
                    'amount' => $periodSummary['current']['profit'],
                    'amount_last' => $periodSummary['last']['profit'],
                    'amount_shipping' => $periodSummary['current']['profit_shipping'],
                    'change' => $monthProfitChange,
                ],
                'year_to_date' => [
                    'amount' => $yearToDateSummary['current']['profit'],
                    'amount_shipping' => $yearToDateSummary['current']['profit_shipping'],
                    'change' => $yearToDateProfitChange,
                ],
                'last_year_to_date' => [
                    'amount' => $lastYearToDateSummary['current']['profit'],
                    'amount_shipping' => $lastYearToDateSummary['current']['profit_shipping'],
                    'change' => $lastYearToDateProfitChange,
                ],
                'year' => [
                    'amount' => $yearSummary['current']['profit'],
                    'amount_shipping' => $yearSummary['current']['profit_shipping'],
                    'change' => $yearProfitChange,
                ],
            ],
        ];
    }

    public function getTopBrands(): array
    {
        $invoiceLines = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime($this->period[0])),
            date('Y-m-d 23:59:59', strtotime($this->period[1]))
        );

        $invoiceLinesLastYear = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime('-1 year', strtotime($this->period[0]))),
            date('Y-m-d 23:59:59', strtotime('-1 year', strtotime($this->period[1])))
        );

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

        $units = 0;
        $unitsLastYear = 0;
        $unitsChange = 'inf';

        $revenue = 0;
        $revenueLastYear = 0;
        $revenueChange = 'inf';

        foreach ($topBrands as &$topBrand) {
            $units += $topBrand['units'];
            $unitsLastYear += $topBrand['units_last_year'];

            $revenue += $topBrand['revenue'];
            $revenueLastYear += $topBrand['revenue_last_year'];

            if ($topBrand['units_last_year'] != 0) {
                $topBrand['units_change'] = round((($topBrand['units'] / $topBrand['units_last_year']) - 1) * 100, 1);
            }

            if ($topBrand['revenue_last_year'] != 0) {
                $topBrand['revenue_change'] = round((($topBrand['revenue'] / $topBrand['revenue_last_year']) - 1) * 100, 1);
            }
        }

        if ($unitsLastYear != 0) {
            $unitsChange = round((($units / $unitsLastYear) - 1) * 100, 1);
        }

        if ($revenueLastYear != 0) {
            $revenueChange = round((($revenue / $revenueLastYear) - 1) * 100, 1);
        }

        // Sort brands based on revenue
        usort($topBrands, function ($item1, $item2) {
            return $item2['revenue'] <=> $item1['revenue'];
        });

        return [
            'brands' => array_values($topBrands),
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

    public function getTopArticles(): array
    {
        $invoiceLines = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime($this->period[0])),
            date('Y-m-d 23:59:59', strtotime($this->period[1]))
        );

        $invoiceLinesLastYear = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime('-1 year', strtotime($this->period[0]))),
            date('Y-m-d 23:59:59', strtotime('-1 year', strtotime($this->period[1])))
        );

        $topArticles = [];

        foreach ($invoiceLines as $invoiceLine) {
            if (!isset($topArticles[$invoiceLine->article_number])) {
                $topArticles[$invoiceLine->article_number] = [
                    'article_number' => $invoiceLine->article_number,
                    'name' => $invoiceLine->article_name,
                    'units' => 0,
                    'units_last_year' => 0,
                    'units_change' => 'inf',
                    'revenue' => 0,
                    'revenue_last_year' => 0,
                    'revenue_change' => 'inf'
                ];
            }

            $topArticles[$invoiceLine->article_number]['units'] += $invoiceLine->quantity;
            $topArticles[$invoiceLine->article_number]['revenue'] += $invoiceLine->amount;
        }

        foreach ($invoiceLinesLastYear as $invoiceLine) {
            if (!isset($topArticles[$invoiceLine->article_number])) {
                continue;
            }

            $topArticles[$invoiceLine->article_number]['units_last_year'] += $invoiceLine->quantity;
            $topArticles[$invoiceLine->article_number]['revenue_last_year'] += $invoiceLine->amount;
        }

        foreach ($topArticles as &$article) {
            if ($article['units_last_year'] != 0) {
                $article['units_change'] = round((($article['units'] / $article['units_last_year']) - 1) * 100, 1);
            }

            if ($article['revenue_last_year'] != 0) {
                $article['revenue_change'] = round((($article['revenue'] / $article['revenue_last_year']) - 1) * 100, 1);
            }
        }

        // Sort customers based on amount
        usort($topArticles, function ($item1, $item2) {
            return $item2['revenue'] <=> $item1['revenue'];
        });

        return array_values($topArticles);
    }

    public function getTopCustomers(): array
    {
        $invoiceLines = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime($this->period[0])),
            date('Y-m-d 23:59:59', strtotime($this->period[1]))
        );

        $invoiceLinesLastYear = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime('-1 year', strtotime($this->period[0]))),
            date('Y-m-d 23:59:59', strtotime('-1 year', strtotime($this->period[1])))
        );

        $customerCreditService = new CustomerCreditService();

        $topCustomers = [];

        foreach ($invoiceLines as $invoiceLine) {
            if (!isset($topCustomers[$invoiceLine->customer_number])) {
                $topCustomers[$invoiceLine->customer_number] = [
                    'customer_number' => $invoiceLine->customer_number,
                    'name' => $invoiceLine->customer_name,
                    'country' => $invoiceLine->customer_country,
                    'credit_limit' => $invoiceLine->customer_credit_limit,
                    'credit_balance' => $invoiceLine->customer_credit_balance,
                    'vendora_rating' => $invoiceLine->customer_vendora_rating,
                    'amount_due' => $customerCreditService->getAmountDue($invoiceLine->customer_number)[0],
                    'credit_terms' => $invoiceLine->customer_credit_terms,
                    'average_payment_days' => $customerCreditService->getAveragePaymentDays($invoiceLine->customer_number, 24),
                    'worst_payment_days' => $customerCreditService->getWorstPaymentDays($invoiceLine->customer_number, 24),
                    'amount' => 0,
                    'amount_last_year' => 0,
                    'change' => 'inf',
                ];
            }

            $topCustomers[$invoiceLine->customer_number]['amount'] += $invoiceLine->amount;
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
        $orderQueueService = new WGROrderQueueService();
        $orderQueue = $orderQueueService->getOrderQueue();
        $orders = $orderQueue['queue'];

        $orderPipeline = [];

        if ($orders) {
            foreach ($orders as $order) {
                $customerNumber = $order['vismaCustomerNumber'] ?? '';

                if (!$customerNumber || !in_array($customerNumber, $this->customerNumbers)) {
                    continue;
                }

                $orderPipeline[] = [
                    'customer' => $order['fullName'] ?? '',
                    'value' => round($order['totalAmount'] ?? 0, 2),
                    'shipping_date' => $order['deliveryDate'] ?? ''
                ];
            }
        }

        return $orderPipeline;
    }

    public function getSalesPersonsToplist(): array
    {
        $invoiceLines = $this->getInvoiceLines(
            date('Y-m-01 00:00:00', strtotime($this->period[0])),
            date('Y-m-d 23:59:59', strtotime($this->period[1]))
        );

        $toplist = [];

        foreach ($invoiceLines as $invoiceLine) {
            if (!isset($toplist[$invoiceLine->sales_person_id])) {
                $name = SalesPerson::where('id', $invoiceLine->sales_person_id)->value('name');
                if (!$name) {
                    continue;
                }

                $toplist[$invoiceLine->sales_person_id] = [
                    'sales_person_id' => $invoiceLine->sales_person_id,
                    'sales_person_name' => $name,
                    'amount' => 0,
                ];
            }

            $toplist[$invoiceLine->sales_person_id]['amount'] += $invoiceLine->amount;
        }

        // Sort toplist by amount
        usort($toplist, function ($item1, $item2) {
            return $item2['amount'] <=> $item1['amount'];
        });

        return $toplist;
    }

    public function getCountryChart(array $topCustomers): array
    {
        // Generate country chart
        $countryChart = [];
        $totalCountryAmount = 0;

        foreach ($topCustomers as $topCustomer) {
            if (!isset($countryChart[$topCustomer['country']])) {
                $countryChart[$topCustomer['country']] = 0;
            }
            $countryChart[$topCustomer['country']] += $topCustomer['amount'];
            $totalCountryAmount += $topCustomer['amount'];
        }

        // Transform country chart to percentage
        if ($totalCountryAmount > 0) {
            $newCountryChart = [];

            foreach ($countryChart as $country => $amount) {
                $countryChart[$country] = round($amount / $totalCountryAmount * 100, 2);

                $percentage = round($amount / $totalCountryAmount * 100, 2);
                $newCountryChart[$country . ' (' . $percentage . '%)'] = $percentage;
            }

            $countryChart = $newCountryChart;
        }

        // Sort country chart by amount
        arsort($countryChart);

        return $countryChart;
    }

    private function getSalesData(string $startDate, string $endDate): array
    {
        // Load all invoice lines between the given dates
        $invoiceLines = $this->getInvoiceLines($startDate, $endDate);

        $totalPrice = 0;
        $totalPriceShipping = 0;

        $totalCost = 0;
        $totalCostShipping = 0;

        foreach ($invoiceLines as $invoiceLine) {
            $totalPrice += $invoiceLine->amount;
            $totalCost += $invoiceLine->cost;

            if ($invoiceLine->article_number == 'SHIP25') {
                $totalPriceShipping += $invoiceLine->amount;
                $totalCostShipping += $invoiceLine->cost;
            }
        }

        // Add cost for shipping (account 4092)
        if (!$this->excludeShipping) {
            $transactionService = new TransactionService();
            $accountSummary = $transactionService->getPeriodSummary('4092', $startDate, $endDate);

            $totalCost += ($accountSummary['debit'] - $accountSummary['credit']);
            $totalCostShipping += ($accountSummary['debit'] - $accountSummary['credit']);
        }

        $totalProfit = $totalPrice - $totalCost;
        $totalMargin = ($totalPrice != 0 ? $totalProfit / $totalPrice : 0) * 100;

        $totalProfitShipping = $totalPriceShipping - $totalCostShipping;
        $totalMarginShipping = ($totalPriceShipping != 0 ? $totalProfitShipping / $totalPriceShipping : 0) * 100;

        return [
            'turnover' => round($totalPrice),
            'cost' => round($totalCost),
            'profit' => round($totalProfit),
            'margin' => round($totalMargin, 1),

            'turnover_shipping' => round($totalPriceShipping),
            'cost_shipping' => round($totalCostShipping),
            'profit_shipping' => round($totalProfitShipping),
            'margin_shipping' => round($totalMarginShipping, 1),
        ];
    }

    private function loadData(): void
    {
        // Load customers connected to the sales person
        $customersQuery = DB::table('customers')
            ->select('customers.id', 'customers.external_id', 'customers.customer_number', 'customers.name', 'customers.country')
            ->leftJoin('sales_people', 'sales_people.external_id', '=', 'customers.sales_person_id');

        if ($this->salesPersonIDs) {
            $salesPersonIDs = [];
            $hasEmptySalesPerson = false;

            foreach ($this->salesPersonIDs as $salesPersonID) {
                if ($salesPersonID == -1) {
                    $hasEmptySalesPerson = true;
                    continue;
                }

                $salesPersonIDs[] = $salesPersonID;
            }

            if ($hasEmptySalesPerson) {
                $customersQuery->where(function($query) use ($salesPersonIDs) {
                    $query->whereIn('sales_people.id', $salesPersonIDs)
                        ->orWhereNull('sales_people.id');
                });
            }
            else {
                $customersQuery->whereIn('sales_people.id', $salesPersonIDs);
            }
        }
        else {
            // Exclude certain sales persons
            $customersQuery->whereNotIn('sales_people.id', self::EXCLUDE_SALES_PERSONS);
        }

        $customers = $customersQuery->get()->toArray();

        $this->customerNumbers = array_map(function ($customer) {
            return $customer->customer_number;
        }, $customers);

        // Filter results for only one customer?
        if ($this->customerNumber) {
            if (in_array($this->customerNumber, $this->customerNumbers)) {
                $this->customerNumbers = [$this->customerNumber];
            } else {
                $this->customerNumbers = [];
            }
        }

        // Load all invoice lines
        $startDate = date('Y-m-d', strtotime('-2 year'));
        $endDate = date('Y-m-d');

        $invoiceLineQuery = DB::table('customer_invoice_lines')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->leftJoin('customers', 'customers.customer_number', '=', 'customer_invoices.customer_number')
            ->leftJoin('articles', 'articles.article_number', '=', 'customer_invoice_lines.article_number')
            ->leftJoin('suppliers', 'suppliers.number', '=', 'articles.supplier_number')
            ->select(
                'customer_invoices.customer_number',
                'customers.name AS customer_name',
                'customers.country AS customer_country',
                'customers.credit_limit AS customer_credit_limit',
                'customers.credit_balance AS customer_credit_balance',
                'customers.credit_terms AS customer_credit_terms',
                'customers.vendora_rating AS customer_vendora_rating',
                'suppliers.number AS supplier_number',
                'suppliers.name AS supplier_name',
                'customer_invoice_lines.article_number',
                'customer_invoice_lines.quantity',
                'customer_invoice_lines.unit_price',
                'customer_invoice_lines.amount',
                'customer_invoice_lines.cost',
                'customer_invoice_lines.sales_person_id',
                'customer_invoices.date',
                'articles.description AS article_name'
            )
            ->whereIn('customer_invoices.customer_number', $this->customerNumbers)
            ->where('customer_invoices.date', '>=', $startDate)
            ->where('customer_invoices.date', '<=', $endDate);

        if ($this->excludeShipping) {
            $invoiceLineQuery->whereNotIn('customer_invoice_lines.article_number', ['SHIP25']);
        }

        if ($this->supplierNumber) {
            $invoiceLineQuery->where('suppliers.number', '=', $this->supplierNumber);
        }

        $this->invoiceLines = $invoiceLineQuery->get()->toArray();
    }

    private function getInvoiceLines(string $startDate, string $endDate): array
    {
        // Return all invoice lines between the given dates
        return array_filter($this->invoiceLines, function ($invoiceLine) use ($startDate, $endDate) {
            return $invoiceLine->date >= $startDate && $invoiceLine->date <= $endDate;
        });
    }
}
