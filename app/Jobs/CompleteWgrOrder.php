<?php

namespace App\Jobs;

use App\Http\Controllers\WgrController;
use App\Mail\WgrActivationFail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        Log::channel('shipments')->info('CompleteWgrOrder request made. Response: ' . json_encode($response), ['wgrOrderID' => $this->wgrOrderID]);

        $result = $response['0']['result'] ?? false;
        $requireActivation = $response['0']['requireActivation'] ?? false;
        $activateSuccess = $response['0']['activateSuccess'] ?? false;

        if ($requireActivation && !$activateSuccess) {
            // Failed to activate the payment in WGR, notify admin about this
            Mail::to('info@vendora.se')
                ->cc('anton@vendora.se')
                ->send(new WgrActivationFail($this->wgrOrderID));
        }

        if (!$result) {
            throw new \Exception('Failed to complete order in WGR. API Response: ' . json_encode($response));
        }
    }
}
