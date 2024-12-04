<?php

namespace App\Jobs;

use App\Http\Controllers\WgrController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteWgrOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $wgrOrderID;
    private string $trackingNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->wgrOrderID = (int) ($data['wgr_order_id'] ?? 0);
        $this->trackingNumber = (string) ($data['tracking_number'] ?? '');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $wgrController = new WgrController();

        $response = $wgrController->makeRequest('order.setTrackingNumber', [
            'id' => $this->wgrOrderID,
            'trackingNumber' => $this->trackingNumber
        ]);

        $result = $response['0']['result'] ?? false;

        if (!$result) {
            throw new \Exception('Failed to complete order in WGR. API Response: ' . json_encode($response));
        }
    }
}
