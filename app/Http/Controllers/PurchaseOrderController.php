<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(PurchaseOrder::class, $request);

        $query = $this->getQueryWithFilter(PurchaseOrder::class, $filter);

        $orders = $query->with('lines')->get();

        return ApiResponseController::success($orders->toArray());
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
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $orderLine->{$key} = $value;
                    }
                }

                $orderLine->save();
            }
        }

        return ApiResponseController::success([$order->toArray()]);
    }
}
