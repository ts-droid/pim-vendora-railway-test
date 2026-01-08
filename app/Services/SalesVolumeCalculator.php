<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

class SalesVolumeCalculator
{
    public function calculateTotalSales(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $salesData = DB::table('customer_invoice_lines')
            ->select('article_number', DB::raw('SUM(quantity) as total_sales'))
            ->groupBy('article_number')
            ->get();

        foreach ($salesData as $data) {
            DB::table('articles')
                ->where('article_number', '=', $data->article_number)
                ->update(['total_sales' => (int) $data->total_sales]);
        }
    }

    /**
     * Calculates the sales volume for each article and store it in the database
     *
     * @return void
     */
    public function calculateArticles(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $currentYear = date('Y');
        $lastYear = $currentYear - 1;
        $twoYearsAgo = $currentYear - 2;

        // Calculate the periods
        $periods = [
            'sales_7_days_last_year' => [
                date('Y-m-d', strtotime('-372 days')),
                date('Y-m-d', strtotime('-365 dats'))
            ],
            'sales_30_days_last_year' => [
                date('Y-m-d', strtotime('-395 days')),
                date('Y-m-d', strtotime('-365 dats'))
            ],
            'sales_60_days_last_year' => [
                date('Y-m-d', strtotime('-425 days')),
                date('Y-m-d', strtotime('-365 dats'))
            ],
            'sales_90_days_last_year' => [
                date('Y-m-d', strtotime('-455 days')),
                date('Y-m-d', strtotime('-365 dats'))
            ],
            'sales_180_days_last_year' => [
                date('Y-m-d', strtotime('-545 days')),
                date('Y-m-d', strtotime('-365 dats'))
            ],
            'sales_7_days' => [
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            ],
            'sales_30_days' => [
                date('Y-m-d', strtotime('-30 days')),
                date('Y-m-d')
            ],
            'sales_60_days' => [
                date('Y-m-d', strtotime('-60 days')),
                date('Y-m-d')
            ],
            'sales_90_days' => [
                date('Y-m-d', strtotime('-90 days')),
                date('Y-m-d')
            ],
            'sales_180_days' => [
                date('Y-m-d', strtotime('-180 days')),
                date('Y-m-d')
            ],
            'sales_365_days' => [
                date('Y-m-d', strtotime('-365 days')),
                date('Y-m-d')
            ],
            'sales_last_year' => [
                date('Y-m-d', strtotime('-365 days')),
                date('Y-m-d', strtotime('-335 days')),
            ],
            'total_sales_year_0' => [
                $currentYear . '-01-01',
                $currentYear . '-12-31'
            ],
            'total_sales_year_1' => [
                $lastYear . '-01-01',
                $lastYear . '-12-31'
            ],
            'total_sales_year_2' => [
                $twoYearsAgo . '-01-01',
                $twoYearsAgo . '-12-31'
            ],
        ];

        // Extract the min and max dates
        $minDate = '';
        $maxDate = '';

        foreach ($periods as $period) {
            list($startDate, $endDate) = $period;

            if ($minDate === '' || $startDate < $minDate) {
                $minDate = $startDate;
            }

            if ($maxDate === '' || $endDate > $maxDate) {
                $maxDate = $endDate;
            }
        }

        // Load all invoice lines for the period
        $invoiceLines = DB::table('customer_invoice_lines')
            ->select(
                'customer_invoice_lines.article_number',
                'customer_invoice_lines.quantity',
                'customer_invoices.date'
            )
            ->leftJoin('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->where('customer_invoices.date', '>=', $minDate)
            ->where('customer_invoices.date', '<=', $maxDate)
            ->get();

        foreach ($periods as $column => $period) {
            list($startDate, $endDate) = $period;

            $articleSummary = [];

            // Calculate the sales volume for each article
            foreach ($invoiceLines as $invoiceLine) {
                if ($invoiceLine->date < $startDate || $invoiceLine->date > $endDate) {
                    continue;
                }

                if (!isset($articleSummary[$invoiceLine->article_number])) {
                    $articleSummary[$invoiceLine->article_number] = 0;
                }

                $articleSummary[$invoiceLine->article_number] += (int) $invoiceLine->quantity;
            }

            // Reset sales volume for all articles
            DB::table('articles')->update([$column => 0]);

            // Update the sales volume for each article
            foreach ($articleSummary as $articleNumber => $salesVolume) {
                DB::table('articles')->where('article_number', (string) $articleNumber)
                    ->update([$column => $salesVolume]);
            }
        }
    }
}
