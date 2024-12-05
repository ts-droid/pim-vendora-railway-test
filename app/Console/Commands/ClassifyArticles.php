<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:classify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify articles based on their sales data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articleNumbers = DB::table('articles')->select('article_number')->pluck('article_number');

        $articleData = [];
        foreach ($articleNumbers as $articleNumber) {
            $salesVolume = DB::table('customer_invoice_lines')
                ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
                ->select('customer_invoice_lines.quantity')
                ->where('customer_invoice_lines.article_number', '=', $articleNumber)
                ->where('customer_invoices.date', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->sum('customer_invoice_lines.quantity');

            $articleData[] = [
                'article_number' => $articleNumber,
                'sales_volume' => $salesVolume
            ];
        }

        // Sort articles by sales volume
        usort($articleData, function ($a, $b) {
            return $a['sales_volume'] < $b['sales_volume'];
        });

        for ($i = 1;$i <= count($articleData);$i++) {
            $article = $articleData[$i - 1];

            if ($i <= 25) {
                $classification = 'A';
            }
            else if ($i <= 150) {
                $classification = 'B';
            }
            else {
                $classification = 'C';
            }

            DB::table('articles')
                ->where('article_number', '=', $article['article_number'])
                ->update(['classification' => $classification]);
        }

        $this->info('Updated article classifications');
    }
}
