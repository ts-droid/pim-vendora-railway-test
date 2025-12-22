<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\VismaNetController;
use App\Models\Customer;
use App\Services\CustomerCreditService;
use App\Services\VismaNet\VismaNetCustomerInvoiceService;
use App\Services\VismaNet\VismaNetCustomerPaymentService;
use App\Services\VismaNet\VismaNetInventoryAdjustmentService;
use App\Services\VismaNet\VismaNetInventoryIssueService;
use App\Services\VismaNet\VismaNetInventoryTransferService;
use App\Services\VismaNet\VismaNetLedgerService;
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
            case 'inventory-adjustments':
                $inventoryAdjustmentService = new VismaNetInventoryAdjustmentService();
                $inventoryAdjustmentService->fetchInventoryAdjustments();
                break;

            case 'inventory-issues':
                $inventoryIssueService = new VismaNetInventoryIssueService();
                $inventoryIssueService->fetchInventoryIssues();
                break;

            case 'inventory-transfers':
                $inventoryTransferService = new VismaNetInventoryTransferService();
                $inventoryTransferService->fetchInventoryTransfers();
                break;

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

            case 'ledger-transactions':
                $ledgerService = new VismaNetLedgerService();
                $ledgerService->fetchTransactions();
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
                // Currently empty :(
                break;

            case 'twicedaily':
                // Fetch all data from Visma
                $this->info('Fetching customers...');
                Process::timeout(7200)->run('php artisan visma:fetch customers');

                $this->info('Fetching sales persons...');
                Process::timeout(7200)->run('php artisan visma:fetch sales-persons');

                $this->info('Fetching suppliers...');
                Process::timeout(7200)->run('php artisan visma:fetch suppliers');

                $this->info('Fetching currency...');
                Process::timeout(7200)->run('php artisan visma:fetch currency');

                $this->info('Fetching transactions...');
                Process::timeout(7200)->run('php artisan visma:fetch transactions');

                $this->info('Fetching invoices...');
                Process::timeout(7200)->run('php artisan visma:fetch invoices');

                // Calculate customer credit values
                $this->info('Calculating customer credit balance...');
                $this->calculateCustomersCreditBalance();
                break;

            case 'hourly':
                $this->info('Fetching credit notes...');
                Process::timeout(3600)->run('php artisan visma:fetch credit-notes');

                // $this->info('Fetching ledger transactions...');
                // Process::timeout(3600)->run('php artisan visma:fetch ledger-transactions');
                break;

            case 'quick':
                $this->info('Fetching inventory adjustments...');
                Process::timeout(300)->run('php artisan visma:fetch inventory-adjustments');

                $this->info('Fetching inventory issues...');
                Process::timeout(300)->run('php artisan visma:fetch inventory-issues');

                $this->info('Fetching inventory transfers...');
                Process::timeout(300)->run('php artisan visma:fetch inventory-transfers');

                $this->info('Fetching purchase orders...');
                Process::timeout(300)->run('php artisan visma:fetch purchase-orders');

                $this->info('Fetching purchase receipts...');
                Process::timeout(300)->run('php artisan visma:fetch purchase-receipts');

                $this->info('Fetching inventory receipts...');
                Process::timeout(300)->run('php artisan visma:fetch inventory-receipts');

                $this->info('Fetching sales orders...');
                Process::timeout(300)->run('php artisan visma:fetch sales-orders');

                $this->info('Fetching shipments...');
                Process::timeout(300)->run('php artisan visma:fetch shipments');

                $this->info('Fetching articles...');
                Process::timeout(600)->run('php artisan visma:fetch articles');
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
