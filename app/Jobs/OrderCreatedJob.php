<?php

namespace App\Jobs;

use App\Enums\LaravelQueues;
use App\Models\SalesOrder;
use App\Services\VismaNet\VismaNetSalesOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private SalesOrder $salesOrder;

    public function __construct(SalesOrder $salesOrder)
    {
        action_log('Invoked job method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        $this->salesOrder = $salesOrder;
    }

    public function handle()
    {
        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        // Send to Visma.net
        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $vismaNetSalesOrderService->sendSalesOrder($this->salesOrder);

        // Queue creation of shipment
        CreateSalesOrderShipmentJob::dispatch($this->salesOrder)
            ->delay(now()->addSeconds(10))
            ->onQueue(LaravelQueues::DEFAULT->value);
    }
}
