<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiResponseController;
use Illuminate\Http\Request;
use App\Models\SalesOrder;

class SalesOrderApiController
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        $salesOrders = SalesOrder::query()
            ->with('customer')
            ->latest()
            ->paginate($perPage);

        return ApiResponseController::success([
            'orders' => $salesOrders->items(),
            'meta' => [
                'current_page' => $salesOrders->currentPage(),
                'last_page' => $salesOrders->lastPage(),
                'per_page' => $salesOrders->perPage(),
                'total' => $salesOrders->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {

    }

    public function show(SalesOrder $salesOrder)
    {

    }

    public function update(Request $request, SalesOrder $salesOrder)
    {

    }
}
