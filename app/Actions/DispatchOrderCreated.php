<?php

namespace App\Actions;

use App\Enums\LaravelQueues;
use App\Jobs\OrderCreatedJob;
use App\Models\SalesOrder;

class DispatchOrderCreated
{
    public function execute(SalesOrder $salesOrder): void
    {
        OrderCreatedJob::dispatch($salesOrder)
            ->delay(now()->addSeconds(10))
            ->onQueue(LaravelQueues::DEFAULT->value);

        action_log('Dispatched order created job.', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);
    }
}
