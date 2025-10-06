<?php

namespace App\Actions;

use App\Jobs\UpdateArticleJob;
use Illuminate\Support\Facades\DB;

class DispatchArticleUpdate
{
    private array $ignoredFields = [
        'cost_price_avg',
        'stock',
        'stock_warehouse',
        'stock_on_hand',
        'stock_available_for_shipment',
        'total_sales',
        'sales_7_days',
        'sales_30_days',
        'sales_60_days',
        'sales_90_days',
        'sales_180_days',
        'sales_365_days',
        'sales_7_days_last_year',
        'sales_30_days_last_year',
        'sales_60_days_last_year',
        'sales_90_days_last_year',
        'sales_180_days_last_year',
        'sales_last_year',
        'total_sales_year_0',
        'total_sales_year_1',
        'total_sales_year_2',
        'bestseller_position',
        'hide_po_system',
        'reseller_url_sv',
        'reseller_url_en',
        'reseller_url_no',
        'reseller_url_fi',
        'reseller_url_da',
        'delivery_days',
        'wgr_id',
        'last_sync_exception',
        'classification',
        'classification_volume',
        'wms_toplist',
        'created_at',
        'updated_at',
        'last_saved'
    ];

    public function execute(int $articleID, bool $isNew, array $changes, bool $force = false): void
    {
        // Check if article is already syncing
        $isSyncing = DB::table('articles')
            ->where('id', $articleID)
            ->pluck('is_syncing')
            ->first();

        if ($isSyncing) {
            // It's already in the queue for syncing, so we don't need to queue it again
            return;
        }


        if ($isNew) {
            // Always dispatch a new article
            $this->setArticleSyncing($articleID);
            UpdateArticleJob::dispatch($articleID, true)
                ->delay(now()->addSeconds(30))
                ->onQueue('article-sync');
        }
        else {
            // Check if changes only contain ignored fields
            $hasChanges = false;
            foreach ($changes as $field => $value) {
                if (!in_array($field, $this->ignoredFields)) {
                    $hasChanges = true;
                    break;
                }
            }

            if ($hasChanges || $force) {
                $this->setArticleSyncing($articleID);
                UpdateArticleJob::dispatch($articleID, false)
                    ->delay(now()->addSeconds(30))
                    ->onQueue('article-sync');
            }
        }
    }

    private function setArticleSyncing(int $articleID): void
    {
        DB::table('articles')->where('id', $articleID)->update(['is_syncing' => 1]);
    }
}
