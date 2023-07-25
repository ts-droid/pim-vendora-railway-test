<?php

namespace App\Console\Commands;

use App\Http\Controllers\VismaAdminController;
use Illuminate\Console\Command;

class FetchVismaAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma-admin:fetch {type=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches data from the Visma admin database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type') ?: 'all';

        $vismaAdminController = new VismaAdminController();

        switch ($type) {
            case 'invoices':
                $vismaAdminController->fetchCustomerInvoices();
                break;

            case 'all':
            default:
                $vismaAdminController->fetchAll();
                break;
        }
    }
}
