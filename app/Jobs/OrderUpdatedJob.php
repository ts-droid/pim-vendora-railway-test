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
        $this->salesOrder = $salesOrder;
    }

    public function handle(): void
    {
        // Send to Visma.net
        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $vismaNetSalesOrderService->updateSalesOrder($this->salesOrder);
    }
}
