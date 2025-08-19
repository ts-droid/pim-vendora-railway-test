<?php

namespace App\Jobs;

use App\Services\PurchaseOrderGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePurchaseOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $supplierID;
    protected int $isEmpty;

    /**
     * Create a new job instance.
     */
    public function __construct(int $supplierID, int $isEmpty)
    {
        $this->supplierID = $supplierID;
        $this->isEmpty = $isEmpty;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $purchaseOrderGenerator = new PurchaseOrderGenerator();

        $purchaseOrderGenerator->generate(
            $this->supplierID,
            $this->isEmpty
        );

        $purchaseOrderGenerator->generateDirect();
    }
}
