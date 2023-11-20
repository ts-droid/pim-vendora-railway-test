<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseSyncService
{
    protected array $tablesToSync = [
        'article_categories',
        'article_images',
        'article_marketing_contents',
        'articles',
        'customer_invoices',
        'customer_invoice_lines',
        'customers',
        'inventory_receipt_lines',
        'inventory_receipts',
        'prompts',
        'purchase_orders',
        'purchase_order_lines',
        'sales_orders',
        'sales_order_lines',
        'sales_people',
        'status_indicators',
        'stock_logs',
        'suppliers',
        'users',
    ];

    public function sync(): void
    {
        dispatch(new \STS\Tunneler\Jobs\CreateTunnel());

        foreach ($this->tablesToSync as $table) {
            $this->syncTable($table);
        }
    }

    private function syncTable(string $table): void
    {
        // Fetch data from production
        $data = DB::connection('mysql_prod')->table($table)->get();

        $rows = $data->toArray();

        $insert = collect();

        foreach ($rows as $row) {
            $newRow = [];

            foreach ($row as $column => $value) {
                $newRow[$column] = $value;
            }

            $insert->push($newRow);
        }

        DB::connection('mysql')->table($table)->truncate();

        $chunks = $insert->chunk(500);
        foreach ($chunks as $chunk) {
            DB::connection('mysql')->table($table)->insert($chunk->toArray());
        }
    }
}
