<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\SupplierController;
use Illuminate\Console\Command;

class MarkSuppliers extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting supplier marking.', $this->commandLogContext());

        $supplierController = new SupplierController();
        $supplierController->markSuppliers();

        action_log('Finished supplier marking.', $this->commandLogContext());
    }
}
