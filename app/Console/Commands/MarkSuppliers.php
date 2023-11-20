<?php

namespace App\Console\Commands;

use App\Http\Controllers\SupplierController;
use Illuminate\Console\Command;

class MarkSuppliers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mark-suppliers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all suppliers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supplierController = new SupplierController();
        $supplierController->markSuppliers();
    }
}
