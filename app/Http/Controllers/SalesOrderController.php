<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesOrderController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(SalesOrder::class, $request);

        $query = $this->getQueryWithFilter(SalesOrder::class, $filter);

        $orders = $query->with('lines')->get();
        $orders = $orders->toArray();

        return ApiResponseController::success($orders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_type' => 'required|string',
            'order_number' => 'required|string',
            'status' => 'required|string',
            'date' => 'required|string',
            'currency' => 'required|string',
            'order_total' => 'required',
            'exchange_rate' => 'required',
            'lines' => 'required|array',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $salesOrder = SalesOrder::create([
            'order_type' => (string) $request->order_type,
            'order_number' => (string) $request->order_number,
            'customer_ref_no' => (string) ($request->customer_ref_no ?? ''),
            'status' => (string) $request->status,
            'date' => (string) $request->date,
            'currency' => (string) $request->currency,
            'order_total' => (float) $request->order_total,
            'exchange_rate' => (float) $request->exchange_rate,
            'invoice_number' => (string) ($request->invoice_number ?? ''),
            'sales_person' => (string) ($request->sales_person ?? ''),
            'customer' => (string) ($request->customer ?? ''),
            'note' => (string) ($request->note ?? ''),
            'on_hold' => (int) ($request->on_hold ?? 0),
        ]);

        $totalQuantity = 0;

        foreach ($request->lines as $line)
        {
            $quantity = (int) $line['quantity'];

            $totalQuantity += $quantity;

            $orderLine = SalesOrderLine::create([
                'sales_order_id' => $salesOrder->id,
                'line_number' => (int) $line['line_number'],
                'article_number' => (string) $line['article_number'],
                'invoice_number' => (string) ($line['invoice_number'] ?? ''),
                'sales_person' => (string) ($line['sales_person'] ?? ''),
                'quantity' => $quantity,
                'quantity_on_shipments' => (int) ($line['quantity_on_shipments'] ?? 0),
                'quantity_open' => (int) ($line['quantity_open'] ?? 0),
                'unit_cost' => (float) $line['unit_cost'],
                'unit_price' => (float) $line['unit_price'],
                'description' => (string) $line['description'],
                'is_completed' => (int) $line['is_completed'],
            ]);
        }

        $salesOrder->update(['order_total_quantity' => $totalQuantity]);

        $salesOrder->refresh();
        $salesOrder->load('lines');

        return ApiResponseController::success($salesOrder->toArray());
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $fillables = (new SalesOrder)->getFillable();
        $lineFillables = (new SalesOrderLine)->getFillable();

        $forceOrderLines = (bool) $request->input('force_order_lines', 0);

        // Update the sales order
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $salesOrder->{$key} = $value;
            }
        }

        $salesOrder->save();

        // Update the lines
        $totalQuantity = 0;
        $updatedLineNumbers = [];

        foreach (($request->lines ?? []) as $line) {
            $salesOrderLine = SalesOrderLine::where([
                ['sales_order_id', '=', $salesOrder->id],
                ['line_number', '=', $line['line_number']],
            ])->first();

            if ($salesOrderLine) {
                // Update existing line
                foreach ($line as $key => $value) {
                    if (in_array($key, $lineFillables)) {
                        $salesOrderLine->{$key} = $value;
                    }
                }

                if ($salesOrderLine->quantity == 0) {
                    $salesOrderLine->delete();
                }
                else {
                    $salesOrderLine->save();

                    $updatedLineNumbers[] = $line['line_number'];
                }
            }
            else {
                // Create a new order line
                $createData = [];

                foreach ($line as $key => $value) {
                    if (in_array($key, $lineFillables)) {
                        $createData[$key] = $value;
                    }
                }

                $createData['sales_order_id'] = $salesOrder->id;

                $salesOrderLine = SalesOrderLine::create($createData);

                $updatedLineNumbers[] = $line['line_number'];
            }

            $totalQuantity += (int) $salesOrderLine->quantity;
        }

        // Delete removed order lines
        if ($forceOrderLines) {
            SalesOrderLine::where('sales_order_id', '=', $salesOrder->id)
                ->whereNotIn('line_number', $updatedLineNumbers)
                ->delete();
        }

        $salesOrder->update(['order_total_quantity' => $totalQuantity]);

        $salesOrder->refresh();
        $salesOrder->load('lines');

        return ApiResponseController::success($salesOrder->toArray());
    }
}
