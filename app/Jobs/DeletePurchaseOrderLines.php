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

class DeletePurchaseOrderLines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public array $orderLines
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lines = [];

        foreach ($this->orderLines as $orderLine) {
            $lines[] = [
                'operation' => 'Delete',
                'lineNumber' => array('value' => $orderLine->line_number),
            ];
        }

        if (!count($lines)) {
            return;
        }

        $apiService = new VismaNetApiService();

        $apiService->callAPI('PUT', '/v1/purchaseorderbasic/' . $this->purchaseOrder->order_number, [
            'lines' => $lines
        ]);
    }
}
