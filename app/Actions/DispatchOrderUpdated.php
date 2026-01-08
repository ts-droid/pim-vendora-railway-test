<?php

namespace App\Actions;

use App\Enums\LaravelQueues;
use App\Jobs\OrderUpdatedJob;
use App\Models\SalesOrder;

class DispatchOrderUpdated
{
    public function execute(SalesOrder $salesOrder): void
    {
        OrderUpdatedJob::dispatch($salesOrder)
            ->delay(now()->addSeconds(10))
            ->onQueue(LaravelQueues::DEFAULT->value);

        action_log('Dispatched order updated job.', [
            'sales_order_id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
        ]);
    }
}
