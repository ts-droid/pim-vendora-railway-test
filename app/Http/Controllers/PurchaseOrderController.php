<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\PurchaseOrderPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(PurchaseOrder::class, $request);

        $query = $this->getQueryWithFilter(PurchaseOrder::class, $filter);

        $orders = $query->with('lines')->get();
        $orders = $orders->toArray();

        // Convert results to requested currency
        $convertToCurrency = $request->get('convert_to_currency', '');
        if ($convertToCurrency) {

            $currencyConverter = new CurrencyConvertController();

            foreach ($orders as &$order) {
                // Convert main order
                $currencyConverter->convertArray($order, ['amount'], 'SEK', $convertToCurrency, $order['date']);

                // Convert order lines
                if ($order['lines']) {
                    foreach ($order['lines'] as &$line) {
                        $currencyConverter->convertArray($line, ['unit_cost', 'amount'], 'SEK', $convertToCurrency, $order['date']);
                    }
                }
            }
        }

        return ApiResponseController::success($orders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'date' => 'required|string',
            'currency' => 'required|string',
            'amount' => 'required|numeric',
            'lines' => 'required|array',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $order = PurchaseOrder::create([
            'order_number' => (string) ($request->order_number ?? ''),
            'status' => (string) ($request->status ?? ''),
            'date' => (string) ($request->date ?? ''),
            'promised_date' => (string) ($request->promised_date ?? ''),
            'supplier_id' => (string) ($request->supplier_id ?? ''),
            'supplier_number' => (string) ($request->supplier_id ?? ''),
            'supplier_name' => (string) ($request->supplier_id ?? ''),
            'currency' => (string) ($request->currency ?? ''),
            'amount' => (float) ($request->amount ?? ''),
            'is_draft' => (int) ($request->is_draft ?? 0),
        ]);

        foreach ($request->lines as $line) {
            $orderLine = PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'line_key' => (string) ($line['line_key'] ?? ''),
                'article_number' => (string) ($line['article_number'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                'amount' => (float) ($line['amount'] ?? 0),
                'promised_date' => (string) ($line['promised_date'] ?? ''),
            ]);
        }

        return ApiResponseController::success([$order->toArray()]);
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $fillables = (new PurchaseOrder)->getFillable();
        $fillablesLine = (new PurchaseOrderLine)->getFillable();

        // Update the order
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $order->{$key} = $value;
            }
        }

        $order->save();

        // Update the lines
        foreach (($request->lines ?? []) as $line) {
            $orderLine = PurchaseOrderLine::where([
                ['purchase_order_id', '=', $order->id],
                ['line_key', '=', $line['line_key']]
            ])->first();

            if ($orderLine) {
                // Update existing line
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $orderLine->{$key} = $value;
                    }
                }

                if ($orderLine->quantity == 0) {
                    $orderLine->delete();
                }
                else {
                    $orderLine->save();
                }
            }
            else {
                // Create a new order line
                $createData = [];

                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $createData[$key] = $value;
                    }
                }

                $createData['purchase_order_id'] = $order->id;

                PurchaseOrderLine::create($createData);
            }
        }

        $order->refresh();

        return ApiResponseController::success([$order->toArray()]);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplierEmail = $purchaseOrder->supplier->email ?? null;

        if (!$supplierEmail) {
            return ApiResponseController::error('Supplier is missing email.');
        }

        try {
            Mail::to($supplierEmail)->queue(new \App\Mail\PurchaseOrder($purchaseOrder));
        } catch (\Exception $e) {
            return ApiResponseController::error($e->getMessage());
        }

        return ApiResponseController::success();
    }

    public function publish(Request $request, PurchaseOrder $order)
    {
        $purchaseOrderPublisher = new PurchaseOrderPublisher();
        $response = $purchaseOrderPublisher->publishOrder($order);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        $order->refresh();

        return ApiResponseController::success([$order->toArray()]);
    }
}
