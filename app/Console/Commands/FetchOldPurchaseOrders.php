<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\PurchaseOrderController;
use App\Models\Supplier;
use App\Services\RemoteDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class FetchOldPurchaseOrders extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch-old-purchase-orders {host} {port} {database} {username} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports old purchase orders from the remote database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = (string) $this->argument('host');
        $port = (string) $this->argument('port');
        $databaseName = (string) $this->argument('database');

        action_log('Starting old purchase order fetch.', $this->commandLogContext([
            'remote_host' => $host,
            'remote_port' => $port,
            'remote_database' => $databaseName,
        ]));

        $database = new RemoteDatabaseService(
            $host,
            $port,
            $databaseName,
            (string) $this->argument('username'),
            (string) $this->argument('password'),
        );

        $orders = $database->fetchAll(
            'SELECT *
            FROM BST
            WHERE LEVERERAD = 1
                AND MAKUL = 0'
        );

        if (empty($orders)) {
            $this->error('No old purchase orders found.');
            action_log('No old purchase orders found on remote source.', $this->commandLogContext([
                'remote_host' => $host,
            ]), 'warning');
            return;
        }

        $orderController = new PurchaseOrderController();
        $imported = 0;
        $skippedExisting = 0;

        foreach ($orders as $order) {
            $orderNumber = 'OLD-' . $order['BSTNR'];
            $supplierNumber = $order['LEVNR'];

            // Check if the purchase order already exists in the local database
            if (DB::table('purchase_orders')->where('order_number', $orderNumber)->exists()) {
                $skippedExisting++;
                continue;
            }

            // Fetch the supplier
            $supplier = Supplier::where('number', $supplierNumber)->first();

            $orderData = [
                'order_number' => $orderNumber,
                'status' => 'Closed',
                'date' => date('Y-m-d', strtotime($order['BESTDAT'])),
                'promised_date' => date('Y-m-d', strtotime($order['BESTDAT'])),
                'supplier_id' => $supplier->id ?? '',
                'supplier_number' => $supplier->number ?? '',
                'supplier_name' => $supplier->name ?? '',
                'currency' => $order['VALUTAKOD'],
                'amount' => (float) $order['SUMMA'],
                'is_draft' => 0,
                'lines' => [],
            ];

            // Fetch all order lines
            $rows = $database->fetchAll(
                'SELECT *
                FROM ARTRAD
                WHERE DOKNR = ?
                    AND TYP = \'B\'',
                array($order['BSTNR'])
            );

            if ($rows) {
                $lineKey = 0;

                foreach ($rows as $row) {
                    $orderData['lines'][] = [
                        'line_key' => $lineKey++,
                        'article_number' => $row['ARTNR'],
                        'description' => $row['TXT'],
                        'quantity' => (int) $row['ANTAL1'],
                        'quantity_received' => (int) $row['ANTAL1'],
                        'unit_cost' => (float) $row['PRIS_ST_V'],
                        'amount' => (float) $row['BELOPP_V'],
                        'promised_date' => date('Y-m-d', strtotime($order['BESTDAT'])),
                        'is_completed' => 1,
                        'is_canceled' => 0,
                    ];
                }
            }

            // Import $orderData to the local database
            $orderController->store(new Request($orderData));
            $imported++;
        }

        action_log('Finished old purchase order import.', $this->commandLogContext([
            'remote_host' => $host,
            'orders_found' => count($orders),
            'orders_imported' => $imported,
            'orders_skipped_existing' => $skippedExisting,
        ]));
    }
}
