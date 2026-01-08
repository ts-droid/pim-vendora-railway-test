<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\ConfigController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyArticles extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:classify {type=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify articles based on their sales data';

    private $config;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type') ?: 'all';

        action_log('Starting article classification.', $this->commandLogContext([
            'type' => $type,
        ]));

        $this->loadConfig();

        $executed = [];

        if ($type == 'all' || $type == 'salesvolume') {
            $this->classifySalesVolume();
            $executed[] = 'salesvolume';
        }
        if ($type == 'all' || $type == 'volume') {
            $this->classifyVolume();
            $executed[] = 'volume';
        }
        if ($type == 'all' || $type == 'wms') {
            $this->classifyWMS();
            $executed[] = 'wms';
        }

        $this->info('Updated article classifications');

        action_log('Finished article classification.', $this->commandLogContext([
            'type' => $type,
            'executed_steps' => $executed,
        ]));
    }

    private function loadConfig()
    {
        $this->config = [
            'article_sales_class_size_a' => (int) ConfigController::getConfig('article_sales_class_size_a', 0),
            'article_sales_class_size_b' => (int) ConfigController::getConfig('article_sales_class_size_b', 0),
            'article_sales_class_history' => (int) ConfigController::getConfig('article_sales_class_history', 0),
            'article_sales_class_unique_invoices' => (int) ConfigController::getConfig('article_sales_class_unique_invoices', 0),
            'sales_class_calculation' => (string) ConfigController::getConfig('sales_class_calculation', 'sales'),
            'article_volume_class_breakpoint_a' => (float) ConfigController::getConfig('article_volume_class_breakpoint_a', 0),
            'article_volume_class_breakpoint_b' => (float) ConfigController::getConfig('article_volume_class_breakpoint_b', 0)
        ];
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
                    if ($volume <= $this->config['article_volume_class_breakpoint_a']) {
                        $classification = 'A';
                    }
                    else if ($volume <= $this->config['article_volume_class_breakpoint_b']) {
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

    private function classifyWMS()
    {
        $salesData = DB::table('shipment_lines')
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->selectRaw('shipment_lines.article_number, SUM(shipment_lines.quantity) AS sum_quantity')
            ->where('shipments.date', '>', date('Y-m-d', strtotime('-30 days')))
            ->groupBy('shipment_lines.article_number')
            ->get()
            ->toArray();

        usort($salesData, function ($a, $b) {
            return $a->sum_quantity < $b->sum_quantity;
        });

        // Reset toplist
        DB::table('articles')->update(['wms_toplist' => 0]);

        // Set the new toplist
        $toplistIndex = 1;
        foreach ($salesData as $row) {
            DB::table('articles')
                ->where('article_number', '=', $row->article_number)
                ->update(['wms_toplist' => $toplistIndex]);

            $toplistIndex++;
        }

        // Get array with only article numbers
        $articleNumbers = array_column($salesData, 'article_number');

        $unknownArticles = DB::table('articles')
            ->select('article_number')
            ->whereNotIn('article_number', $articleNumbers)
            ->pluck('article_number');

        foreach ($unknownArticles as $articleNumber) {
            DB::table('articles')
                ->where('article_number', '=', $articleNumber)
                ->update(['wms_toplist' => $toplistIndex]);

            $toplistIndex++;
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
                ->where('customer_invoices.date', '>=', date('Y-m-d H:i:s', strtotime('-' . $this->config['article_sales_class_history'] . ' days')))
                ->first();

            $articleData[] = [
                'article_number' => $articleNumber,
                'sales_volume' => $salesData->total_quantity,
                'unique_invoices' => $salesData->unique_invoices,
            ];
        }

        // Sort articles based on calculation type
        switch ($this->config['sales_class_calculation']) {
            case 'both':
                usort($articleData, function ($a, $b) {
                    $aScore = ($a['unique_invoices'] * 10) + $a['sales_volume'];
                    $bScore = ($b['unique_invoices'] * 10) + $b['sales_volume'];

                    return $aScore < $bScore;
                });
                break;

            case 'invoices':
                usort($articleData, function ($a, $b) {
                    return $a['unique_invoices'] < $b['unique_invoices'];
                });
                break;

            case 'sales':
            default:
                usort($articleData, function ($a, $b) {
                    return $a['sales_volume'] < $b['sales_volume'];
                });
                break;
        }

        $classCounts = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
        ];

        for ($i = 1;$i <= count($articleData);$i++) {
            $article = $articleData[$i - 1];

            if ($classCounts['A'] < $this->config['article_sales_class_size_a']) {
                if ($article['unique_invoices'] >= $this->config['article_sales_class_unique_invoices']) {
                    $classification = 'A';
                    $classCounts['A']++;
                }
                else {
                    $classification = 'B';
                    $classCounts['B']++;
                }
            }
            else if ($classCounts['B'] < $this->config['article_sales_class_size_b']) {
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
