<?php

namespace App\Jobs;

use App\Http\Controllers\ApiResponseController;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendPurchaseOrderReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public Collection $orderLines
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipients = [$this->purchaseOrder->email ?: $this->purchaseOrder->supplier->email];

        $recipients = ['anton@scriptsector.se'];

        try {
            Mail::to($recipients)->send(new \App\Mail\PurchaseOrderReminder($this->purchaseOrder, $this->orderLines));
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
