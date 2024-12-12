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
        $this->classifySalesVolume();
        $this->classifyVolume();

        $this->info('Updated article classifications');
    }

    private function classifyVolume()
    {
        $articles = DB::table('articles')
            ->select('id', 'height', 'width', 'depth')
            ->get();

        if ($articles) {
            foreach ($articles as $article) {
                $volume = ($article->height / 1000) * ($article->width / 1000) * ($article->depth / 1000);

                $classification = '';

                if ($volume) {
                    if ($volume < 0.001) {
                        $classification = 'A';
                    }
                    else if ($volume < 0.005) {
                        $classification = 'B';
                    }
                    else {
                        $classification = 'C';
                    }
                }

                DB::table('articles')
                    ->where('id', $article->id)
                    ->update(['classification_volume' => $classification]);
            }
        }
    }

    private function classifySalesVolume()
    {
        $articleNumbers = DB::table('articles')->select('article_number')->pluck('article_number');

        $articleData = [];
        foreach ($articleNumbers as $articleNumber) {
            $salesData = DB::table('customer_invoice_lines')
                ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
                ->selectRaw('SUM(customer_invoice_lines.quantity) as total_quantity, COUNT(DISTINCT customer_invoice_lines.customer_invoice_id) as unique_invoices')
                ->where('customer_invoice_lines.article_number', '=', $articleNumber)
                ->where('customer_invoices.date', '>=', date('Y-m-d H:i:s', strtotime('-120 days')))
                ->first();

            $articleData[] = [
                'article_number' => $articleNumber,
                'sales_volume' => $salesData->total_quantity,
                'unique_invoices' => $salesData->unique_invoices,
            ];
        }

        // Sort articles by sales volume
        usort($articleData, function ($a, $b) {
            return $a['sales_volume'] < $b['sales_volume'];
        });

        $classCounts = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
        ];

        for ($i = 1;$i <= count($articleData);$i++) {
            $article = $articleData[$i - 1];

            if ($classCounts['A'] < 35) {
                if ($article['unique_invoices'] >= 3) {
                    $classification = 'A';
                    $classCounts['A']++;
                }
                else {
                    $classification = 'B';
                    $classCounts['B']++;
                }
            }
            else if ($classCounts['B'] < 150) {
                $classification = 'B';
                $classCounts['B']++;
            }
            else {
                $classification = 'C';
                $classCounts['C']++;
            }

            DB::table('articles')
                ->where('article_number', '=', $article['article_number'])
                ->update(['classification' => $classification]);
        }
    }
}
