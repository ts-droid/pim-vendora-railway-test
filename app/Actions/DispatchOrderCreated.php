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
    }
}
