<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\PurchaseOrderWeightGenerator;
use Illuminate\Console\Command;

class GeneratePurchaseOrderWeights extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-order:generate-weights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates purchase order weights';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting purchase order weight generation.', $this->commandLogContext());

        $service = new PurchaseOrderWeightGenerator();
        $service->generateMonthWeights();

        action_log('Finished purchase order weight generation.', $this->commandLogContext());
    }
}
