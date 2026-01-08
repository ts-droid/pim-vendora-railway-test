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

    protected array $localTableColumns = [];

    public function sync(): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        dispatch(new \STS\Tunneler\Jobs\CreateTunnel());

        foreach ($this->tablesToSync as $table) {
            $this->syncTable($table);
        }
    }

    private function syncTable(string $table): void
    {
        // Truncate table
        DB::connection('mysql')->table($table)->truncate();

        // Fetch data from production to local
        $data = DB::connection('mysql_prod')
            ->table($table)
            ->orderBy('id')
            ->chunk(500, function($rows) use ($table) {

                $insert = collect();

                foreach ($rows as $row) {
                    $newRow = [];

                    foreach ($row as $column => $value) {
                        if (!$this->tableHasColumn($table, $column)) {
                            continue;
                        }

                        $newRow[$column] = $value;
                    }

                    $insert->push($newRow);
                }

                DB::connection('mysql')->table($table)->insert($insert->toArray());
            });
    }

    /**
     * Returns true if the table has the column.
     *
     * @param string $table
     * @param string $column
     * @return mixed
     */
    private function tableHasColumn(string $table, string $column)
    {
        if (!isset($this->localTableColumns[$table])) {
            $this->localTableColumns[$table] = [];
        }

        if (!isset($this->localTableColumns[$table][$column])) {
            $this->localTableColumns[$table][$column] = DB::connection('mysql')->getSchemaBuilder()->hasColumn($table, $column);
        }

        return $this->localTableColumns[$table][$column];
    }
}
