<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiResponseController;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Validator;

class SalesOrderApiController
{
    protected SalesOrderService $orderService;

    public function __construct(SalesOrderService $orderService)
    {
        $this->orderService = $orderService;
    }


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
        $validator = Validator::make($request->all(), [
            'order_type' => 'required|string|max:255',
            'order_number' => 'sometimes|string|max:255',
            'order_number_prefix' => 'sometimes|string|max:2',
            'sales_person' => 'sometimes|string|max:255',
            'customer_number' => 'sometimes|string|max:255',
            'currency' => 'required|string|min:3|max:3',
            'note' => 'sometimes|string',
            'internal_note' => 'sometimes|string',
            'store_note' => 'sometimes|string',
            'source' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|max:255',
            'billing_email' => 'required|string|max:255',
            'pay_method' => 'required|string|max:255',

            'billing_full_name' => 'required|string|max:255',
            'billing_first_name' => 'required|string|max:255',
            'billing_last_name' => 'required|string|max:255',
            'billing_street_line_1' => 'required|string|max:255',
            'billing_street_line_2' => 'sometimes|string|max:255',
            'billing_postal_code' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_country_code' => 'required|string|max:255',

            'shipping_full_name' => 'required|string|max:255',
            'shipping_first_name' => 'required|string|max:255',
            'shipping_last_name' => 'required|string|max:255',
            'shipping_street_line_1' => 'required|string|max:255',
            'shipping_street_line_2' => 'sometimes|string|max:255',
            'shipping_postal_code' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:255',
            'shipping_country_code' => 'required|string|max:255',

            'lines' => 'required|array|min:1',

            'lines.*.article_number' => 'required|string|max:255',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.quantity_on_shipments' => 'nullable|integer|min:0',
            'lines.*.quantity_open' => 'nullable|integer|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $salesOrder = $this->orderService->createSalesOrder($request->all());

        return ApiResponseController::success($salesOrder->toArray());
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load('customer', 'lines', 'billingAddress', 'shippingAddress', 'logs');

        return ApiResponseController::success($salesOrder->toArray());
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $validator = Validator::make($request->all(), [
            'order_type' => 'sometimes|string|max:255',
            'sales_person' => 'sometimes|string|max:255',
            'customer_number' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|min:3|max:3',
            'note' => 'sometimes|string',
            'internal_note' => 'sometimes|string',
            'store_note' => 'sometimes|string',
            'source' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|max:255',
            'billing_email' => 'sometimes|string|max:255',
            'pay_method' => 'sometimes|string|max:255',

            'billing_full_name' => 'sometimes|string|max:255',
            'billing_first_name' => 'sometimes|string|max:255',
            'billing_last_name' => 'sometimes|string|max:255',
            'billing_street_line_1' => 'sometimes|string|max:255',
            'billing_street_line_2' => 'sometimes|string|max:255',
            'billing_postal_code' => 'sometimes|string|max:255',
            'billing_city' => 'sometimes|string|max:255',
            'billing_country_code' => 'sometimes|string|max:255',

            'shipping_full_name' => 'sometimes|string|max:255',
            'shipping_first_name' => 'sometimes|string|max:255',
            'shipping_last_name' => 'sometimes|string|max:255',
            'shipping_street_line_1' => 'sometimes|string|max:255',
            'shipping_street_line_2' => 'sometimes|string|max:255',
            'shipping_postal_code' => 'sometimes|string|max:255',
            'shipping_city' => 'sometimes|string|max:255',
            'shipping_country_code' => 'sometimes|string|max:255',

            'lines' => 'sometimes|array|min:1',

            'lines.*.article_number' => 'required|string|max:255',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.quantity_on_shipments' => 'nullable|integer|min:0',
            'lines.*.quantity_open' => 'nullable|integer|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $salesOrder = $this->orderService->updateSalesOrder($salesOrder, $request->all());

        return ApiResponseController::success($salesOrder->toArray());
    }
}
