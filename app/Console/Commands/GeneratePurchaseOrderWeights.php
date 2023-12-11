<?php

namespace App\Console\Commands;

use App\Services\PurchaseOrderWeightGenerator;
use Illuminate\Console\Command;

class GeneratePurchaseOrderWeights extends Command
{
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
        $service = new PurchaseOrderWeightGenerator();
        $service->generateMonthWeights();
    }
}
