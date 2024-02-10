<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\VismaNetController;
use App\Models\Customer;
use App\Services\CustomerCreditService;
use App\Services\VismaNet\VismaNetCustomerPaymentService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use App\Services\VismaNet\VismaNetTransactionService;
use Illuminate\Console\Command;

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
                $vismaNetController->fetchCustomerInvoices();
                break;

            case 'purchase-orders':
                $vismaNetController->fetchPurchaseOrders();
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

            case 'daily':
                // Fetch all data from Visma
                $this->call('visma:fetch', ['type' => 'customers']);
                $this->call('visma:fetch', ['type' => 'sales-persons']);
                $this->call('visma:fetch', ['type' => 'suppliers']);
                $this->call('visma:fetch', ['type' => 'invoices']);
                $this->call('visma:fetch', ['type' => 'purchase-orders']);
                $this->call('visma:fetch', ['type' => 'inventory-receipts']);
                $this->call('visma:fetch', ['type' => 'currency']);
                $this->call('visma:fetch', ['type' => 'sales-orders']);
                $this->call('visma:fetch', ['type' => 'transactions']);
                $this->call('visma:fetch', ['type' => 'payments']);

                // Calculate customer credit values
                $customers = Customer::all();
                if ($customers) {
                    $customerCreditService = new CustomerCreditService();

                    foreach ($customers as $customer) {
                        $customerCreditService->calculateCustomerCreditBalance($customer);
                        $customerCreditService->calculateVendoraRating($customer);
                    }
                }
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
}
