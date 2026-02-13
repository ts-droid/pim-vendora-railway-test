<?php

namespace App\Http\Controllers\Api;

use App\Enums\LaravelQueues;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Jobs\OrderCreatedJob;
use App\Models\Shipment;
use App\Services\SalesOrderService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesOrderApiController extends Controller
{
    protected SalesOrderService $orderService;

    public function __construct(SalesOrderService $orderService)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $cacheKey = 'sales_orders_page_' . $page . '_per_page_' . $perPage;

        $salesOrders = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($perPage) {
            return SalesOrder::query()
                ->with('customer', 'billingAddress', 'shippingAddress')
                ->orderBy('has_sync_error', 'DESC')
                ->latest()
                ->paginate($perPage);
        });

        $sources = Cache::remember('sales_order_sources', now()->addMinutes(2), function () {
            return DB::table('sales_orders')
                ->distinct()
                ->pluck('source');
        });

        return ApiResponseController::success([
            'orders' => $salesOrders->items(),
            'sources' => $sources,
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
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $validator = Validator::make($request->all(), [
            'order_type' => 'required|string|max:255',
            'order_number' => 'sometimes|string|max:255',
            'order_number_prefix' => 'sometimes|string|max:2',
            'sales_person' => 'sometimes|string|max:255',
            'customer_number' => 'sometimes|string|max:255',
            'currency' => 'required|string|min:3|max:3',
            'language' => 'sometimes|string|min:2|max:2',
            'note' => 'sometimes|string',
            'internal_note' => 'sometimes|string',
            'store_note' => 'sometimes|string',
            'source' => 'required|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|max:255',
            'billing_email' => 'sometimes|string|max:255',
            'pay_method' => 'required|string|max:255',
            'store_pay_method' => 'sometimes|string|max:255',
            'vat_number' => 'sometimes|string|max:255',
            'is_company' => 'sometimes|integer|min:0|max:1',
            'payment_reference' => 'sometimes|string|max:255',

            'billing_full_name' => 'sometimes|string|max:255',
            'billing_first_name' => 'sometimes|string|max:255',
            'billing_last_name' => 'sometimes|string|max:255',
            'billing_street_line_1' => 'sometimes|string|max:255',
            'billing_street_line_2' => 'sometimes|string|max:255',
            'billing_postal_code' => 'sometimes|string|max:255',
            'billing_city' => 'sometimes|string|max:255',
            'billing_country_code' => 'sometimes|string|max:255',

            'shipping_full_name' => 'sometimes|string|max:255',
            'shipping_attention' => 'sometimes|string|max:255',
            'shipping_first_name' => 'sometimes|string|max:255',
            'shipping_last_name' => 'sometimes|string|max:255',
            'shipping_street_line_1' => 'sometimes|string|max:255',
            'shipping_street_line_2' => 'sometimes|string|max:255',
            'shipping_postal_code' => 'sometimes|string|max:255',
            'shipping_city' => 'sometimes|string|max:255',
            'shipping_country_code' => 'sometimes|string|max:255',

            'lines' => 'required|array|min:1',

            'lines.*.article_number' => 'required|string|max:255',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.quantity_on_shipments' => 'nullable|integer|min:0',
            'lines.*.quantity_open' => 'nullable|integer|min:0',
            'lines.*.unit_price' => 'required|numeric',
            'lines.*.active_unit_price' => 'sometimes|numeric',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        try {
            $salesOrder = $this->orderService->createSalesOrder($request->all());
        } catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }

        return ApiResponseController::success($salesOrder->toArray());
    }

    public function show(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

         try {
             $salesOrder->load('customer', 'lines', 'billingAddress', 'shippingAddress', 'logs');

             $salesOrder->shipments = Shipment::whereJsonContains('order_numbers', $salesOrder->order_number)->get();

             return ApiResponseController::success($salesOrder->toArray());
         } catch (\Throwable $e) {
                return ApiResponseController::error($e->getMessage());
         }
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'order_type' => 'sometimes|string|max:255',
            'sales_person' => 'sometimes|string|max:255',
            'customer_number' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|min:3|max:3',
            'language' => 'sometimes|string|min:2|max:2',
            'note' => 'sometimes|string',
            'internal_note' => 'sometimes|string',
            'store_note' => 'sometimes|string',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|max:255',
            'billing_email' => 'sometimes|string|max:255',
            'pay_method' => 'sometimes|string|max:255',
            'store_pay_method' => 'sometimes|string|max:255',
            'vat_number' => 'sometimes|string|max:255',
            'is_company' => 'sometimes|integer|min:0|max:1',
            'payment_reference' => 'sometimes|string|max:255',

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
            'shipping_attention' => 'sometimes|string|max:255',
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
            'lines.*.unit_price' => 'required|numeric',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $salesOrder = $this->orderService->updateSalesOrder($salesOrder, $request->all());

        return ApiResponseController::success($salesOrder->toArray());
    }

    public function cancel(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $salesOrderService = new SalesOrderService();
        $response = $salesOrderService->cancelSalesOrder($salesOrder);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }

    public function resetSync(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $success = $vismaNetSalesOrderService->resetSalesOrder($salesOrder);

        if (!$success) {
            return ApiResponseController::error('Failed to reset sales order sync.');
        }

        $salesOrder->update([
            'order_number' => '1' . $salesOrder->order_number
        ]);

        OrderCreatedJob::dispatch($salesOrder)
            ->onQueue(LaravelQueues::DEFAULT->value);

        return ApiResponseController::success();
    }

    public function createShipment(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        try {
            $vismaNetSalesOrderService = new VismaNetSalesOrderService();
            $vismaNetSalesOrderService->createShipment($salesOrder);

            return ApiResponseController::success();
        } catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }

    public function orderCreatedJob(SalesOrder $salesOrder)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        OrderCreatedJob::dispatch($salesOrder)
            ->onQueue(LaravelQueues::DEFAULT->value);

        return ApiResponseController::success();
    }
}
