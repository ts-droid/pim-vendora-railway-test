<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

class SalesVolumeCalculator
{
    /**
     * Calculates the sales volume for each article and store it in the database
     *
     * @return void
     */
    public function calculateArticles(): void
    {
        // Calculate the periods
        $periods = [
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
            'sales_last_year' => [
                date('Y-m-d', strtotime('-365 days')),
                date('Y-m-d', strtotime('-335 days')),
            ]
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

        // Load all order lines for the period
        $invoiceLines = DB::table('customer_invoice_lines')
            ->select('customer_invoice_lines.*', 'customer_invoices.date')
            ->leftJoin('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->where('customer_invoices.date', '>=', $startDate)
            ->where('customer_invoices.date', '<=', $endDate)
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
            Article::update([$column => 0]);

            // Update the sales volume for each article
            foreach ($articleSummary as $articleNumber => $salesVolume) {
                Article::where('article_number', $articleNumber)->update([$column => $salesVolume]);
            }
        }
    }
}
