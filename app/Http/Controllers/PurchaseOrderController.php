<?php

namespace App\Http\Controllers;

use App\Jobs\RegeneratePurchaseOrder;
use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Services\ArticleQuantityCalculator;
use App\Services\PurchaseOrderCancelService;
use App\Services\PurchaseOrderDeletionService;
use App\Services\PurchaseOrderEmailer;
use App\Services\PurchaseOrderGenerator;
use App\Services\PurchaseOrderPublisher;
use App\Services\PurchaseOrderReminderService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierArticlePriceService;
use App\Services\VismaNet\VismaNetPurchaseOrderService;
use App\Services\WMS\StockItemService;
use App\Services\WMS\StockPlaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function getOngoing(Request $request)
    {
        $purchaseOrderLines = DB::table('purchase_order_lines')
            ->select(
                'purchase_order_lines.*',
                'purchase_orders.supplier_name',
                'purchase_orders.date',
                'purchase_orders.order_number',
                'suppliers.supplier_contact_email as email'
            )
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.external_id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_order_lines.is_completed', '=', 0)
            ->where(function ($query) {
                $query->where('purchase_order_lines.promised_date', '<', date('Y-m-d'))
                    ->orWhereNull('purchase_order_lines.promised_date');
            })
            ->where('purchase_orders.status', '=', 'Open')
            ->whereNull('purchase_order_lines.reminder_sent_at')
            ->where('purchase_orders.should_delete', '=', 0)
            ->orderBy('purchase_orders.id')
            ->get();

        return ApiResponseController::success($purchaseOrderLines->toArray());
    }

    public function getOngoingSent()
    {
        $purchaseOrderLines = DB::table('purchase_order_lines')
            ->select(
                'purchase_order_lines.*',
                'purchase_orders.supplier_name',
                'purchase_orders.date',
                'purchase_orders.order_number',
                'suppliers.supplier_contact_email as email'
            )
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.external_id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_order_lines.is_completed', '=', 0)
            ->where('purchase_orders.status', '=', 'Open')
            ->whereNotNull('purchase_order_lines.reminder_sent_at')
            ->where('purchase_orders.should_delete', '=', 0)
            ->orderBy('purchase_orders.id')
            ->get();

        return ApiResponseController::success($purchaseOrderLines->toArray());
    }

    public function getOngoingDeleted()
    {
        $purchaseOrders = PurchaseOrder::where('should_delete', '=', 1)
            ->where('purchase_orders.status', '=', 'Open')
            ->where(function ($query) {
                $query->whereNull('user_deleted_at')
                    ->orWhere('user_deleted_at', '<', date('Y-m-d H:i:s', strtotime('-1 day')));
            })
            ->orderBy('id')
            ->get();

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function getShipment(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderShipment $purchaseOrderShipment)
    {
        $purchaseOrderShipment->load('lines');

        return ApiResponseController::success($purchaseOrderShipment->toArray());
    }

    public function submitManualShipment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrderService = new PurchaseOrderService();

        $quantities = $request->input('quantities', []);
        if (!$quantities) {
            return ApiResponseController::error('No quantities provided.');
        }

        // Load all open lines
        $lines = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->where('purchase_order_shipment_id', 0)
            ->get();

        $deliveredLines = [];
        $deliveredQuantities = [];

        foreach ($lines as $line) {
            $qty = $quantities[$line->id] ?? null;
            if (is_null($qty) || !$qty) continue;

            $qty = (int) $qty;

            if ($qty > $line->quantity) {
                return ApiResponseController::error('One or more lines have a quantity greater than the ordered quantity.');
            }

            if ($qty == $line->quantity) {
                // The entire line is delivered
                $deliveredLines[] = $line;
                $deliveredQuantities[$line->id] = $qty;
            }
            else {
                // The line is partially delivered, so we need to split it
                $response = $purchaseOrderService->splitOrderLine($line, $qty);
                if (!$response['success']) {
                    return ApiResponseController::error($response['error_message']);
                }

                $newLine = $response['new_line'];
                $deliveredLines[] = $newLine;
                $deliveredQuantities[$newLine->id] = $qty;
            }
        }

        if (count($deliveredLines) === 0) {
            return ApiResponseController::error('No lines were delivered.');
        }

        // Create a new shipment for the delivered lines and deliver it
        $purchaseOrderService = new PurchaseOrderService();
        $purchaseOrderShipment = $purchaseOrderService->createShipment(
            $purchaseOrder,
            [],
            $deliveredLines
        );

        $response = $purchaseOrderService->deliverShipment(
            $purchaseOrderShipment,
            $deliveredQuantities
        );

        if (!$response['success']) {
            return ApiResponseController::error($response['error_message']);
        }

        return ApiResponseController::success($purchaseOrder->toArray());
    }

    public function submitShipment(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderShipment $purchaseOrderShipment)
    {
        $purchaseOrderService = new PurchaseOrderService();
        $response = $purchaseOrderService->deliverShipment(
            $purchaseOrderShipment,
            $request->input('quantities', [])
        );

        if (!$response['success']) {
            return ApiResponseController::error($response['error_message']);
        }

        return ApiResponseController::success([]);
    }

    public function getOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $loadRelations = $request->input('load_relations', '0');

        if ($loadRelations) {
            $purchaseOrder->load('supplier', 'lines', 'lines.article');

            if ($purchaseOrder->lines) {
                foreach ($purchaseOrder->lines as &$orderLine) {
                    $orderLine->incoming_quantity = ArticleQuantityCalculator::getIncoming($orderLine['article_number']);
                    $orderLine->incoming_by_date = ArticleQuantityCalculator::getIncomingByDate($orderLine['article_number']);
                    $orderLine->on_order_quantity = ArticleQuantityCalculator::getOnOrder($orderLine['article_number']);
                    $orderLine->on_order_by_date = ArticleQuantityCalculator::getOnOrderByDate($orderLine['article_number']);
                }
            }

            $purchaseOrder->shipments = PurchaseOrderShipment::where('purchase_order_id', $purchaseOrder->id)
                ->with('lines')
                ->orderBy('id', 'DESC')
                ->get();
        }

        return ApiResponseController::success($purchaseOrder->toArray());
    }

    public function get(Request $request)
    {
        $loadRelations = $request->get('load_relations', '1');

        $filter = $this->getModelFilter(PurchaseOrder::class, $request);

        $query = $this->getQueryWithFilter(PurchaseOrder::class, $filter);

        if ($loadRelations) {
            $query->with('supplier', 'lines', 'lines.article');
        }

        $orders = $query->orderBy('id', 'DESC')->get();
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

        // Load order lines data
        if ($loadRelations) {
            foreach ($orders as &$order) {
                if (!$order['lines']) {
                    continue;
                }

                foreach ($order['lines'] as &$orderLine) {
                    $orderLine['incoming_quantity'] = ArticleQuantityCalculator::getIncoming($orderLine['article_number']);
                    $orderLine['incoming_by_date'] = ArticleQuantityCalculator::getIncomingByDate($orderLine['article_number']);
                    $orderLine['on_order_quantity'] = ArticleQuantityCalculator::getOnOrder($orderLine['article_number']);
                    $orderLine['on_order_by_date'] = ArticleQuantityCalculator::getOnOrderByDate($orderLine['article_number']);
                }
            }
        }

        return ApiResponseController::success($orders);
    }

    public function getWarehouse(Request $request)
    {
        $purchaseOrders = PurchaseOrder::where('status', '!=', 'Closed')
            ->where('is_draft', 0)
            ->where('is_po_system', 1)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($purchaseOrders as &$purchaseOrder) {
            $purchaseOrder->num_lines = $purchaseOrder->lines->count();
            $purchaseOrder->tracking_numbers = $purchaseOrder->lines->pluck('tracking_number')->unique()->toArray();

            // Check if the order has open shipments
            $hasOpenShipments = false;

            foreach ($purchaseOrder->lines as $line) {
                if ($line->purchase_order_shipment_id && !$line->is_completed) {
                    $hasOpenShipments = true;
                    break;
                }
            }

            $purchaseOrder->has_open_shipments = $hasOpenShipments;
        }

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function getOpen(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        $purchaseOrders = PurchaseOrder::where('status', 'Draft')
            ->where('is_draft', 1)
            ->where('is_sent', 0)
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        if ($purchaseOrders->count()) {
            foreach ($purchaseOrders as &$purchaseOrder) {
                $purchaseOrder->not_shipped_value = $purchaseOrder->getNotShippedValue();
            }
        }

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function getPending(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        $purchaseOrders = PurchaseOrder::whereIn('status', ['Draft', 'Open'])
            ->where('is_draft', 0)
            ->where('is_po_system', 1)
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        if ($purchaseOrders->count()) {
            foreach ($purchaseOrders as &$purchaseOrder) {
                $purchaseOrder->not_shipped_value = $purchaseOrder->getNotShippedValue();
            }
        }

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function getClosed(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        $purchaseOrders = PurchaseOrder::whereNotIn('status', ['Draft', 'Open'])
            ->where('is_po_system', 1)
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function search(Request $request)
    {
        $perPage = $request->get('per_page', 30);
        $search = $request->get('search', '');

        if (!$search) {
            return ApiResponseController::error('No search provided');
        }

        $purchaseOrders = PurchaseOrder::where('is_po_system', 1)
            ->where(function($query) use ($search) {
                $query->where('id', $search)
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('lines', function ($q) use ($search) {
                        $q->where('article_number', 'LIKE', $search);
                    });
            })
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        return ApiResponseController::success($purchaseOrders->toArray());
    }

    public function generatingIds()
    {
        $ids = PurchaseOrder::where('is_generating', 1)->pluck('id');

        return ApiResponseController::success($ids->toArray());
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
            'order_number' => (string)($request->order_number ?? ''),
            'status' => (string)($request->status ?? ''),
            'date' => (string)($request->date ?? ''),
            'promised_date' => (string)($request->promised_date ?? ''),
            'supplier_id' => (string)($request->supplier_id ?? ''),
            'supplier_number' => (string)($request->supplier_id ?? ''),
            'supplier_name' => (string)($request->supplier_id ?? ''),
            'currency' => (string)($request->currency ?? ''),
            'amount' => (float)($request->amount ?? ''),
            'is_draft' => (int)($request->is_draft ?? 0),
        ]);

        foreach ($request->lines as $line) {
            $orderLine = PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'line_key' => (string)($line['line_key'] ?? ''),
                'article_number' => (string)($line['article_number'] ?? ''),
                'description' => (string)($line['description'] ?? ''),
                'quantity' => (int)($line['quantity'] ?? 0),
                'unit_cost' => (float)($line['unit_cost'] ?? 0),
                'amount' => (float)($line['amount'] ?? 0),
                'promised_date' => (string)($line['promised_date'] ?? ''),
            ]);
        }

        return ApiResponseController::success([$order->toArray()]);
    }

    public function addRow(Request $request, PurchaseOrder $purchaseOrder)
    {
        $article = Article::where('article_number', $request->get('article_number'))->first();

        if (!$article) {
            return ApiResponseController::error('Article not found.');
        }

        // Make sure it is an active article
        if ($article->status != 'Active') {
            return ApiResponseController::error('This article is not active.');
        }

        // Check if the article already exists in the order
        $existingLine = PurchaseOrderLine::where([
            ['purchase_order_id', '=', $purchaseOrder->id],
            ['article_number', '=', $article->article_number],
        ])->first();

        if ($existingLine) {
            return ApiResponseController::error('Article already exists on the order.');
        }

        // Decide the line key
        $lineKey = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->selectRaw('MAX(CAST(line_key AS UNSIGNED)) as max_line_key')
            ->value('max_line_key');

        $lineKey = (int)$lineKey + 1;

        // Decide the quantity
        $quantity = (int)($request->get('quantity') ?? 0);
        $quantity = max(1, $quantity);

        // Get the unit cost for the article
        $supplierPriceService = new SupplierArticlePriceService();
        $unitCost = $supplierPriceService->getUnitCostForSupplier($article->article_number, $purchaseOrder->supplier);

        // Create the order line
        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $purchaseOrder->id,
            'line_key' => $lineKey,
            'article_number' => $article->article_number,
            'description' => $article->description,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => ($unitCost * $quantity),
            'promised_date' => '',
        ]);

        return ApiResponseController::success($line->toArray());
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $requestData = $request->all();

        $fillables = (new PurchaseOrder)->getFillable();
        $fillablesLine = (new PurchaseOrderLine)->getFillable();

        $orderUpdateData = [];

        // Update the order
        foreach ($requestData as $key => $value) {
            if (in_array($key, $fillables)) {
                $orderUpdateData[$key] = $value;
            }
        }

        if ($orderUpdateData) {
            $purchaseOrder->update($orderUpdateData);
        }

        // Update the lines
        $updatedLineKeys = [];

        foreach (($requestData['lines'] ?? []) as $line) {
            $orderLine = PurchaseOrderLine::where([
                ['purchase_order_id', '=', $purchaseOrder->id],
                ['line_key', '=', $line['line_key']]
            ])->first();

            if ($orderLine) {
                $updates = [];

                // Update existing line
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $updates[$key] = $value;
                    }
                }

                $oldUnitCost = $orderLine->unit_cost;
                $unitCost = round($updates['unit_cost'] ?? $orderLine->unit_cost, 2);
                $quantity = $updates['quantity'] ?? $orderLine->quantity;

                $updates['amount'] = $unitCost * $quantity;

                if ($quantity == 0) {
                    $orderLine->delete();
                } else {
                    $orderLine->update($updates);
                }

                // Should we update the unit cost to the pricelist?
                $updatePricelist = (int)($line['update_pricelist'] ?? 0);

                if ($updatePricelist && $oldUnitCost != $unitCost) {
                    $supplierPriceService = new SupplierArticlePriceService();
                    $supplierPriceService->createSupplierArticlePrice([
                        'article_number' => (string)$orderLine->article_number,
                        'price' => $unitCost,
                        'currency' => (string)$purchaseOrder->currency,
                    ]);
                }

                $updatedLineKeys[] = $line['line_key'];
            } else {
                // Create a new order line
                $createData = [];

                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $createData[$key] = $value;
                    }
                }

                if (isset($createData['unit_cost'])) {
                    $createData['unit_cost'] = round($createData['unit_cost'], 2);
                }

                $createData['amount'] = $createData['unit_cost'] * $createData['quantity'];
                $createData['purchase_order_id'] = $purchaseOrder->id;

                PurchaseOrderLine::create($createData);

                $updatedLineKeys[] = $line['line_key'];
            }
        }

        // Delete removed order lines
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->whereNotIn('line_key', $updatedLineKeys)
            ->delete();

        $purchaseOrder->refresh();

        // Calculate the total amount of the order
        $totalAmount = $purchaseOrder->lines->sum(function ($line) {
            return $line->unit_cost * $line->quantity;
        });

        $purchaseOrder->update([
            'amount' => $totalAmount,
        ]);

        return ApiResponseController::success([$purchaseOrder->toArray()]);
    }

    public function regenerate(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->update(['is_generating' => 1]);

        RegeneratePurchaseOrder::dispatch($purchaseOrder)->onQueue('high');

        return ApiResponseController::success();
    }

    public function sendReminders(Request $request)
    {
        $purchaseOrderLineIDs = $request->post('purchase_order_line_ids');
        $emails = $request->post('emails');

        if (!$purchaseOrderLineIDs) {
            return ApiResponseController::error('No order lines selected');
        }

        if (is_string($purchaseOrderLineIDs) || is_numeric($purchaseOrderLineIDs)) {
            $purchaseOrderLineIDs = [$purchaseOrderLineIDs];
        }

        $reminderService = new PurchaseOrderReminderService();
        $reminderService->remindETARequest($purchaseOrderLineIDs, $emails);

        return ApiResponseController::success([]);
    }

    public function cancelOrderLines(Request $request)
    {
        $purchaseOrderLineIDs = $request->post('purchase_order_line_ids');

        if (!$purchaseOrderLineIDs) {
            return ApiResponseController::error('No order lines selected');
        }

        if (is_string($purchaseOrderLineIDs) || is_numeric($purchaseOrderLineIDs)) {
            $purchaseOrderLineIDs = [$purchaseOrderLineIDs];
        }

        // Delete the order lines
        $deleteService = new PurchaseOrderDeletionService();
        $deleteService->deleteLines($purchaseOrderLineIDs);

        return ApiResponseController::success([]);
    }

    public function sendV2(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Send the order to external system
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->send($purchaseOrder);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        // Make sure the order is updated
        $purchaseOrder->refresh();

        // Send the email to the supplier
        $mailer = new PurchaseOrderEmailer();
        $response = $mailer->sendNewOrder($purchaseOrder);

        if (!$response['success']) {
            log_data('Failed to queue emails for purchase order ' . $purchaseOrder->id . ': ' . $response['message']);
        }

        // Should we also generate a new order for this supplier?
        if ($request->get('generate_new_order')) {
            Artisan::call('purchase-orders:generate', ['supplierID' => $purchaseOrder->supplier->id]);
        }

        $purchaseOrder->update([
            'is_sent' => 1,
            'status_sent_to_supplier' => 1,
            'confirm_reminder_sent_at' => date('Y-m-d H:i:s')
        ]);

        return ApiResponseController::success();
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->supplier->id == 202) {
            return $this->sendV2($request, $purchaseOrder);
        }

        // Send the order to external system
        $publisher = new PurchaseOrderPublisher();
        $response = $publisher->send($purchaseOrder);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        // Make sure the order is updated
        $purchaseOrder->refresh();

        // Send the email
        $mailer = new PurchaseOrderEmailer();
        list($success, $message) = $mailer->send($purchaseOrder);

        if (!$success) {
            log_data('Failed to queue emails for purchase order ' . $purchaseOrder->id . ': ' . $message);
        }

        // Should we also generate a new order for this supplier?
        if ($request->get('generate_new_order')) {
            Artisan::call('purchase-orders:generate', ['supplierID' => $purchaseOrder->supplier->id]);
        }

        $purchaseOrder->update([
            'is_sent' => 1,
            'status_sent_to_supplier' => 1,
            'confirm_reminder_sent_at' => date('Y-m-d H:i:s')
        ]);

        return ApiResponseController::success();
    }

    public function publish(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrderPublisher = new PurchaseOrderPublisher();
        $response = $purchaseOrderPublisher->publishOrder($purchaseOrder);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        $purchaseOrder->refresh();

        return ApiResponseController::success([$purchaseOrder->toArray()]);
    }

    public function delete(Request $request, PurchaseOrder $purchaseOrder)
    {
        $deleteService = new PurchaseOrderDeletionService();

        $deleted = $deleteService->delete($purchaseOrder);

        if (!$deleted) {
            return ApiResponseController::error('Could not delete purchase order.');
        }

        return ApiResponseController::success();
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $cancelService = new PurchaseOrderCancelService();

        $response = $cancelService->cancel($purchaseOrder);

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
    }

    public function userDelete(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->update([
            'user_deleted_at' => date('Y-m-d H:i:s'),
        ]);

        return ApiResponseController::success();
    }

    public function draftReminder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $reminderService = new PurchaseOrderReminderService();
        $reminderService->remindPurchaseOrderDraft($purchaseOrder);

        return ApiResponseController::success();
    }

    public function copyLine(Request $request, PurchaseOrder $purchaseOrder)
    {
        $lineID = (int) $request->input('line_id');
        $quantity = (int) $request->input('quantity');

        if (!$lineID || $quantity <= 0) {
            return ApiResponseController::error('Missing or invalid "line_id" or "quantity" parameter.');
        }

        $originalLine = PurchaseOrderLine::find($lineID);

        $purchaseOrderService = new PurchaseOrderService();
        $response = $purchaseOrderService->splitOrderLine($originalLine, $quantity);
        if (!$response['success']) {
            return ApiResponseController::error($response['error_message']);
        }

        return ApiResponseController::success($response['new_line']->toArray());
    }
}
