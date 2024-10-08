<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateLastArticlePurchaseDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate-last-article-purchase-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the last purchase date for all articles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articleNumbers = DB::table('articles')
            ->select('article_number')
            ->get()
            ->pluck('article_number');

        if (!$articleNumbers) {
            return;
        }

        foreach ($articleNumbers as $articleNumber) {
            $lastPurchaseDate = (string) DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                ->select('purchase_orders.date')
                ->where('purchase_order_lines.article_number', '=', $articleNumber)
                ->orderBy('purchase_orders.date', 'DESC')
                ->limit(1)
                ->pluck('purchase_orders.date')
                ->first();

            DB::table('articles')
                ->where('article_number', '=', $articleNumber)
                ->update(['last_purchase_date' => $lastPurchaseDate]);
        }
    }
}
