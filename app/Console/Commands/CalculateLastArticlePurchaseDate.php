<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateLastArticlePurchaseDate extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting last article purchase date calculation.', $this->commandLogContext());

        $articleNumbers = DB::table('articles')
            ->select('article_number')
            ->get()
            ->pluck('article_number');

        if (!$articleNumbers->count()) {
            action_log('No article numbers found for purchase date calculation.', $this->commandLogContext(), 'warning');
            return;
        }

        $updated = 0;

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

            $updated++;
        }

        action_log('Finished last article purchase date calculation.', $this->commandLogContext([
            'articles_processed' => $articleNumbers->count(),
            'articles_updated' => $updated,
        ]));
    }
}
