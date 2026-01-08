<?php

namespace App\Jobs;

use App\Models\SalesOrder;
use App\Services\VismaNet\VismaNetSalesOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderUpdatedJob implements ShouldQueue
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

    public function handle(): void
    {
        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        // Send to Visma.net
        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $vismaNetSalesOrderService->updateSalesOrder($this->salesOrder);
    }
}
