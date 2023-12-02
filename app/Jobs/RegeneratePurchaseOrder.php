<?php

namespace App\Jobs;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegeneratePurchaseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PurchaseOrder $purchaseOrder
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $purchaseOrderGenerator = new PurchaseOrderGenerator();
        $purchaseOrderGenerator->regenerate($this->purchaseOrder);
    }
}
