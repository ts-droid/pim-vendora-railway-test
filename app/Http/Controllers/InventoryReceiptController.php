<?php

namespace App\Http\Controllers;

use App\Events\InventoryReceiptUpdated;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryReceiptController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(InventoryReceipt::class, $request);

        $query = $this->getQueryWithFilter(InventoryReceipt::class, $filter);

        $receipts = $query->with('lines')->get();

        return ApiResponseController::success($receipts->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receipt_number' => 'required|string',
            'date' => 'required|string',
            'status' => 'required|string',
            'total_cost' => 'required|numeric',
            'total_quantity' => 'required|numeric',
            'lines' => 'required|array',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $receipt = InventoryReceipt::create([
            'receipt_number' => (string) ($request->receipt_number ?? ''),
            'date' => (string) ($request->date ?? ''),
            'status' => (string) ($request->status ?? ''),
            'total_cost' => (float) ($request->total_cost ?? ''),
            'total_quantity' => (int) ($request->total_quantity ?? ''),
        ]);

        foreach ($request->lines as $line) {
            $receiptLine = InventoryReceiptLine::create([
                'inventory_receipt_id' => $receipt->id,
                'line_key' => (string) ($line['line_key'] ?? ''),
                'article_number' => (string) ($line['article_number'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'unit_cost' => (float) ($line['unit_cost'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? ''),
                'total_cost' => (float) ($line['total_cost'] ?? ''),
            ]);
        }

        // Dispatch updated event
        InventoryReceiptUpdated::dispatch(
            InventoryReceipt::find($receipt->id)
        );

        return ApiResponseController::success($receipt->toArray());
    }

    public function update(Request $request, InventoryReceipt $receipt)
    {
        $fillables = (new InventoryReceipt)->getFillable();
        $fillablesLine = (new InventoryReceiptLine)->getFillable();

        // Update the order
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {

                if (in_array($key, ['total_cost', 'total_quantity'])) {
                    $value = (int) $value;
                }

                $receipt->{$key} = $value;
            }
        }

        $receipt->save();

        // Update the lines
        foreach (($request->lines ?? []) as $line) {
            $receiptLine = InventoryReceiptLine::where([
                ['inventory_receipt_id', '=', $receipt->id],
                ['line_key', '=', $line['line_key']]
            ])->first();

            if ($receiptLine) {
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {

                        if (in_array($key, ['unit_cost', 'quantity', 'total_cost'])) {
                            $value = (int) $value;
                        }

                        $receiptLine->{$key} = $value;
                    }
                }

                $receiptLine->save();
            }
        }

        // Dispatch updated event
        InventoryReceiptUpdated::dispatch(
            InventoryReceipt::find($receipt->id)
        );

        return ApiResponseController::success($receipt->toArray());
    }
}
