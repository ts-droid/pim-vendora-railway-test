<?php

namespace App\Console\Commands;

use App\Http\Controllers\VismaNetController;
use Illuminate\Console\Command;

class FetchVismaNet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:fetch {type=all}';

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
        $type = $this->argument('type') ?: 'all';

        $vismaNetController = new VismaNetController();

        switch ($type) {
            case 'invoices':
                $vismaNetController->fetchCustomerInvoices();
                break;

            case 'suppliers':
                $vismaNetController->fetchSuppliers();
                break;

            case 'currency':
                $vismaNetController->fetchCurrencyRates();
                break;

            case 'customers':
                $vismaNetController->fetchCustomers();
                break;

            case 'articles':
                $vismaNetController->fetchArticles('', true);
                break;

            case 'all':
            default:
                $vismaNetController->fetchAll();
                break;
        }
    }
}
