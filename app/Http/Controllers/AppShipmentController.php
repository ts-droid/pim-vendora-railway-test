<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentInternalStatus;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\VismaNet\VismaNetApiService;
use App\Services\VismaNet\VismaNetShipmentService;
use Illuminate\Http\Request;

class AppShipmentController extends Controller
{
    public function list(Request $request)
    {
        $shipments = Shipment::where('status', 'Open')
            ->orderBy('id', 'DESC')->limit(10)
            ->with('address', 'lines')
            ->get();

        foreach ($shipments as &$shipment) {
            $shipment->is_backorder = $shipment->isBackorder();
        }

        return ApiResponseController::success($shipments->toArray());
    }

    public function get(Shipment $shipment)
    {
        $shipment->load('address', 'lines', 'lines.article');

        foreach ($shipment->lines as &$line) {
            $line->order_quantity = $line->orderQuantity();
        }

        $shipment->is_backorder = $shipment->isBackorder();

        // Load open siblings
        $shipment->openSiblings = Shipment::where('customer_number', '=', $shipment->customer_number)
            ->where('status', '=', 'Open')
            ->where('internal_status', '=', ShipmentInternalStatus::OPEN)
            ->where('id', '!=', $shipment->id)
            ->with('address', 'lines', 'lines.article')
            ->get()
            ->toArray();

        return ApiResponseController::success($shipment->toArray());
    }

    public function ping(Request $request, Shipment $shipment)
    {
        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
                ->where('status', '=', 'Open')
                ->update(['ping_at' => time()]);
        }
        else {
            $shipment->update(['ping_at' => time()]);
        }

        return ApiResponseController::success();
    }

    public function unping(Request $request, Shipment $shipment)
    {
        if ($request->input('pingAll')) {
            Shipment::where('customer_number', '=', $shipment->customer_number)
                ->where('status', '=', 'Open')
                ->update(['ping_at' => 0]);
        }
        else {
            $shipment->update(['ping_at' => 0]);
        }

        return ApiResponseController::success();
    }

    public function pick(Request $request, Shipment $shipment)
    {
        $investigate = false;

        $lines = $request->input('lines');
        if ($lines && is_array($lines)) {
            foreach ($lines as $line) {
                $lineID = $line['id'] ?? 0;
                $quantity = $line['quantity'] ?? 0;
                $investigationComment = $line['investigation_comment'] ?? '';

                $shipmentLine = ShipmentLine::where('shipment_id', '=', $shipment->id)
                    ->where('id', '=', $lineID)
                    ->first();

                if (!$shipmentLine) continue;

                $shipmentLine->update([
                    'picked_quantity' => $quantity,
                    'investigation_comment' => $investigationComment
                ]);

                if ($quantity > $shipmentLine->quantity || $quantity == 0) {
                    $investigate = true;
                }
            }
        }

        // Update shipment status
        if ($investigate) {
            // Mark for investigation
            $shipment->update([
                'internal_status' => ShipmentInternalStatus::INVESTIGATE,
                'ping_at' => 0
            ]);
        }
        else {
            // Mark as picked
            $shipment->update([
                'internal_status' => ShipmentInternalStatus::PICKED,
                'ping_at' => 0
            ]);
        }

        $shipment->load('address', 'lines', 'lines.article');

        return ApiResponseController::success($shipment->toArray());
    }

    public function complete(Shipment $shipment)
    {
        return ApiResponseController::error('This feature is disabled in demo-mode.');

        // Complete the shipment in Visma.net
        $vismaNetShipmentService = new VismaNetShipmentService();
        $response = $vismaNetShipmentService->completeShipment($shipment);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        // Update internal status
        $shipment->update([
            'internal_status' => ShipmentInternalStatus::PACKED,
            'ping_at' => 0
        ]);

        return ApiResponseController::success();
    }

    public function print(Shipment $shipment)
    {
        $vismaNetApi = new VismaNetApiService();

        $response = $vismaNetApi->callAPI('GET', '/v1/shipment/' . $shipment->number . '/printShipmentConfirmation', [], 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjRCQjQzQzg4QzgzODc1MUI3QTI2MDFEMjg0ODFGNEVDOUQwMUExRUJSUzI1NiIsIng1dCI6IlM3UThpTWc0ZFJ0NkpnSFNoSUgwN0owQm9lcyIsInR5cCI6ImF0K0pXVCJ9.eyJpc3MiOiJodHRwczovL2Nvbm5lY3QudmlzbWEuY29tIiwibmJmIjoxNzMxNDExMDA0LCJpYXQiOjE3MzE0MTEwMDQsImV4cCI6MTczMTQxNDYwNCwiYXVkIjoiaHR0cHM6Ly9pbnRlZ3JhdGlvbi52aXNtYS5uZXQvQVBJL2ludGVyYWN0aXZlIiwic2NvcGUiOlsicHJvZmlsZSIsImVtYWlsIiwib3BlbmlkIiwidGVuYW50cyIsInZpc21hbmV0X2VycF9pbnRlcmFjdGl2ZV9hcGk6dXBkYXRlIiwidmlzbWFuZXRfZXJwX2ludGVyYWN0aXZlX2FwaTpkZWxldGUiLCJ2aXNtYW5ldF9lcnBfaW50ZXJhY3RpdmVfYXBpOmNyZWF0ZSIsInZpc21hbmV0X2VycF9pbnRlcmFjdGl2ZV9hcGk6cmVhZCIsIm9mZmxpbmVfYWNjZXNzIl0sImFtciI6WyJwd2QiXSwiY2xpZW50X2lkIjoiaXN2X2FkbWludmVuZG9yYSIsInRlbmFudF9pZCI6IjUwMmE3NGI4LTcxNTgtMTFlZC05ODkxLTA2OTNkOGE3YzNkZCIsInRlbmFudF9leHRlcm5hbF9pZCI6IjQ0ODk5ODAiLCJ0ZW5hbnRfb3duZXJfY2xpZW50X2lkIjoib2RwIiwic3ViIjoiMGU1NGE3ODgtMDBmZi00MDc5LThlZmYtNGUzMmI3MmM1NTk5IiwiYXV0aF90aW1lIjoxNzIwNzg5MTI0LCJpZHAiOiJWaXNtYSBDb25uZWN0IiwibGx0IjoxNzA4NDQxOTQxLCJjcmVhdGVkX2F0IjoxNjgxMjk0MDAxLCJhY3IiOiIyIiwic2lkIjoiMTYxNjc4YjQtMjI2Mi1kNmJjLWVjNzUtNjcyZjVhODhkNDJmIn0.nLHij8vmPaLLEUdlGIuD_rGuaf2tHwl0a3GOBF23fKgr1hyOhIamDeLHp-r86Q3fHIQeg1cvxgQcwgNJo5B1biWlzLzx9GTeE3OJYSfPyJizaabOeJG7ThDmTQVipYmfPBY7uHuUik3KFYVA99xSgcapH9Ni57j5z0eWX-Bf-xOWlj2K30QmoXXCaNE3SS8Ur4pE5dyK5Iq03Zj09sMCxsLuRVL3sENtknE7LJn3cbUVKDJhVADsy65XL345xeOT-bXajhkfhj56DVXTrqHb9araYqgQYLd7hCk92gqQ1qk4eGdoEk3Wa6MyL_Ze7hWCyVzMjA0RmV2BB-G3yZbnYQ', true);

        $data = $response['response'];

        return response($data, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="shipment-confirmation.pdf"'
        ]);
    }
}
