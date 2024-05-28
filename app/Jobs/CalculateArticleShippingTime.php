<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CalculateArticleShippingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Fetch all purchase orders the last 6 months
        $purchaseOrderLines = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->select(
                'purchase_orders.date',
                'purchase_order_lines.article_number',
                'purchase_order_lines.promised_date'
            )
            ->where('purchase_orders.date', '>=', now()->subMonths(6)->format('Y-m-d'))
            ->where('purchase_orders.status', '=', 'Closed')
            ->get();

        // Calculate average delivery dates for each article
        $deliveryDays = [];

        foreach($purchaseOrderLines as $orderLine) {
            if (!isset($deliveryDays[$orderLine->article_number])) {
                $deliveryDays[$orderLine->article_number] = [];
            }

            $deliveryDays[$orderLine->article_number][] = round((strtotime($orderLine->promised_date) - strtotime($orderLine->date)) / 86400);
        }

        foreach($deliveryDays as $articleNumber => $days) {
            $deliveryDays[$articleNumber] = round(array_sum($days) / count($days));
        }

        // Update the articles with the calculated delivery days
        foreach($deliveryDays as $articleNumber => $days) {
            Article::where('article_number', strval($articleNumber))->update(['delivery_days' => $days]);
        }

        // Reset all other articles to 0 days
        $updatedArticleNumbers = array_keys($deliveryDays);
        $updatedArticleNumbers = array_map('strval', $updatedArticleNumbers);

        Article::whereNotIn('article_number', $updatedArticleNumbers)->update(['delivery_days' => 0]);
    }
}
