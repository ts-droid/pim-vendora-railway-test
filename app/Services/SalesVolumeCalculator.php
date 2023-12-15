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
        $orderLines = DB::table('sales_order_lines')
            ->select(
                'sales_order_lines.article_number', 'sales_order_lines.quantity',
                'sales_orders.date'
            )
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.date', '>=', $minDate)
            ->where('sales_orders.date', '<=', $maxDate)
            ->get();

        foreach ($periods as $column => $period) {
            list($startDate, $endDate) = $period;

            $articleSummary = [];

            // Calculate the sales volume for each article
            foreach ($orderLines as $orderLine) {
                if ($orderLine->date < $startDate || $orderLine->date > $endDate) {
                    continue;
                }

                if (!isset($articleSummary[$orderLine->article_number])) {
                    $articleSummary[$orderLine->article_number] = 0;
                }

                $articleSummary[$orderLine->article_number] += (int) $orderLine->quantity;
            }

            // Reset sales volume for all articles
            Article::query()->update([$column => 0]);

            // Update the sales volume for each article
            foreach ($articleSummary as $articleNumber => $salesVolume) {
                Article::where('article_number', (string) $articleNumber)->update([$column => $salesVolume]);
            }
        }
    }
}
