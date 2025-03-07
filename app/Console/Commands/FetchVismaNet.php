<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\VismaNetController;
use App\Models\Customer;
use App\Services\CustomerCreditService;
use App\Services\VismaNet\VismaNetCustomerInvoiceService;
use App\Services\VismaNet\VismaNetCustomerPaymentService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use App\Services\VismaNet\VismaNetShipmentService;
use App\Services\VismaNet\VismaNetTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class FetchVismaNet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:fetch {type=none}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches new data from Visma.net';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type') ?: 'none';

        $vismaNetController = new VismaNetController();

        switch ($type) {
            case 'customers':
                $vismaNetController->fetchCustomers();
                break;

            case 'sales-persons':
                $vismaNetController->fetchSalesPersons();
                break;

            case 'suppliers':
                $vismaNetController->fetchSuppliers();
                break;

            case 'articles':
                $vismaNetController->fetchArticles('', true);
                break;

            case 'invoices':
                $customerInvoiceService = new VismaNetCustomerInvoiceService();
                $customerInvoiceService->fetchCustomerInvoices();
                break;

            case 'credit-notes':
                $vismaNetController->fetchCustomerCreditNotes();
                break;

            case 'purchase-orders':
                $vismaNetController->fetchPurchaseOrders();
                break;

            case 'purchase-receipts':
                $vismaNetController->fetchPurchaseReceipts();
                break;

            case 'inventory-receipts':
                $vismaNetController->fetchInventoryReceipts();
                break;

            case 'currency':
                $vismaNetController->fetchCurrencyRates();
                break;

            case 'sales-orders':
                $salesOrderService = new VismaNetSalesOrderService();
                $salesOrderService->fetchSalesOrders();
                break;

            case 'transactions':
                $vismaTransactionService = new VismaNetTransactionService();
                $vismaTransactionService->fetchTransactions();
                break;

            case 'payments':
                $vismaNetPaymentService = new VismaNetCustomerPaymentService();
                $vismaNetPaymentService->fetchCustomerPayments();
                break;

            case 'shipments':
                $vismaNetShipmentService = new VismaNetShipmentService();
                $vismaNetShipmentService->fetchShipments();
                break;

            case 'daily':
                // Fetch all data from Visma
                Artisan::call('visma:fetch', ['type' => 'customers']);
                Artisan::call('visma:fetch', ['type' => 'sales-persons']);
                Artisan::call('visma:fetch', ['type' => 'suppliers']);
                Artisan::call('visma:fetch', ['type' => 'currency']);
                Artisan::call('visma:fetch', ['type' => 'transactions']);
                Artisan::call('visma:fetch', ['type' => 'invoices']);

                // Calculate customer credit values
                $this->calculateCustomersCreditBalance();
                break;

            case 'hourly':
                Artisan::call('visma:fetch', ['type' => 'credit-notes']);
                break;

            case 'quick':
                $this->info('Fetching purchase orders...');
                Process::run('php artisan visma:fetch purchase-orders');

                $this->info('Fetching purchase receipts...');
                Process::run('php artisan visma:fetch purchase-receipts');

                $this->info('Fetching inventory receipts...');
                Process::run('php artisan visma:fetch inventory-receipts');

                $this->info('Fetching sales orders...');
                Process::run('php artisan visma:fetch sales-orders');

                $this->info('Fetching shipments...');
                Process::run('php artisan visma:fetch shipments');

                $this->info('Fetching purchase articles...');
                Process::run('php artisan visma:fetch articles');
                break;

            case 'all':
                $vismaNetController->fetchAll();
                break;

            default:
                $this->error('Invalid fetch type.');
                return;
                break;
        }

        StatusIndicatorController::ping('Visma.net sync', 86400);
    }

    private function calculateCustomersCreditBalance()
    {
        $customers = Customer::all();
        if ($customers) {
            $customerCreditService = new CustomerCreditService();

            foreach ($customers as $customer) {
                $customerCreditService->calculateCustomerCreditBalance($customer);
                $customerCreditService->calculateVendoraRating($customer);
                $customerCreditService->calculatePaymentDays($customer);
            }
        }
    }
}
