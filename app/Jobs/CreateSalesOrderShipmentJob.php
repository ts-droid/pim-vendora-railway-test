<?php

namespace App\Jobs;

use App\Models\SalesOrder;
use App\Services\VismaNet\VismaNetSalesOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateSalesOrderShipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private SalesOrder $salesOrder;

    public function __construct(SalesOrder $salesOrder)
    {
        $this->salesOrder = $salesOrder;
    }

    public function handle()
    {
        // Create shipment in Visma.net
        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $vismaNetSalesOrderService->createShipment($this->salesOrder);
    }
}
