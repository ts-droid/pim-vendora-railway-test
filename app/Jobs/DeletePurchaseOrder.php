<?php

namespace App\Jobs;

use App\Models\PurchaseOrder;
use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeletePurchaseOrder implements ShouldQueue
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
        return;

        // Update the local status to "Cancelled"
        $this->purchaseOrder->update([
            'status' => 'Cancelled'
        ]);

        // Update the Visma.net status to "Cancelled"
        $apiService = new VismaNetApiService();

        $apiService->callAPI('PUT', '/v1/purchaseorder/' . $this->purchaseOrder->order_number, [

        ]);
    }
}
