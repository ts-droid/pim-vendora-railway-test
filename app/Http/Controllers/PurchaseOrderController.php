<?php

namespace App\Http\Controllers;

use App\Jobs\RegeneratePurchaseOrder;
use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\ArticleQuantityCalculator;
use App\Services\PurchaseOrderDeletionService;
use App\Services\PurchaseOrderGenerator;
use App\Services\PurchaseOrderPublisher;
use App\Services\PurchaseOrderReminderService;
use App\Services\SupplierArticlePriceService;
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
                'purchase_orders.email'
            )
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_order_lines.is_completed', '=', 0)
            ->where(function($query) {
                $query->where('purchase_order_lines.promised_date', '<', date('Y-m-d'))
                    ->orWhereNull('purchase_order_lines.promised_date');
            })
            ->where('purchase_orders.status', '=', 'Open')
            ->orderBy('purchase_orders.id')
            ->get();

        return ApiResponseController::success($purchaseOrderLines->toArray());
    }

    public function get(Request $request)
    {
        $filter = $this->getModelFilter(PurchaseOrder::class, $request);

        $query = $this->getQueryWithFilter(PurchaseOrder::class, $filter);

        $orders = $query->with('supplier', 'lines', 'lines.article')->get();
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

    public function addRow(Request $request, PurchaseOrder $purchaseOrder)
    {
        $article = Article::where('article_number', $request->get('article_number'))->first();

        if (!$article) {
            return ApiResponseController::error('Article not found.');
        }

        // Decide the line key
        $lineKey = ((int) PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->max('line_key')) + 1;

        // Decide the quantity
        $quantity = (int) ($request->get('quantity') ?? 0);
        $quantity = max(1, $quantity);

        // Get the unit cost for the article
        $supplierPriceService = new SupplierArticlePriceService();
        $unitCost = $supplierPriceService->getUnitCostForSupplier($article->article_number, $purchaseOrder->supplier);

        // Create the order line
        PurchaseOrderLine::create([
            'purchase_order_id' => $purchaseOrder->id,
            'line_key' => $lineKey,
            'article_number' => $article->article_number,
            'description' => $article->description,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => ($unitCost * $quantity),
            'promised_date' => '',
        ]);

        return ApiResponseController::success();
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
                $unitCost = $updates['unit_cost'] ?? $orderLine->unit_cost;
                $quantity = $updates['quantity'] ?? $orderLine->quantity;

                $updates['amount'] = $unitCost * $quantity;

                if ($quantity == 0) {
                    $orderLine->delete();
                }
                else {
                    $orderLine->update($updates);
                }

                // Should we update the unit cost to the pricelist?
                if (!$oldUnitCost && $oldUnitCost != $unitCost) {
                    $supplierPriceService = new SupplierArticlePriceService();
                    $supplierPriceService->createSupplierArticlePrice([
                        'article_number' => $orderLine->article_number,
                        'price' => $unitCost,
                        'currency' => $purchaseOrder->currency,
                    ]);
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

                $createData['amount'] = $createData['unit_cost'] * $createData['quantity'];
                $createData['purchase_order_id'] = $purchaseOrder->id;

                PurchaseOrderLine::create($createData);
            }
        }

        $purchaseOrder->refresh();

        return ApiResponseController::success([$purchaseOrder->toArray()]);
    }

    public function regenerate(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->update(['is_generating' => 1]);

        RegeneratePurchaseOrder::dispatch($purchaseOrder);

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
        $reminderService->remind($purchaseOrderLineIDs, $emails);

        return ApiResponseController::success([]);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder)
    {
        $recipients = [$purchaseOrder->email ?: ($purchaseOrder->supplier->email ?? null)];

        $recipients = ['anton@scriptsector.se', 'ah@vendora.se'];

        // Validate the emails
        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        // Make sure we have at least 1 email address
        if (count($recipients) === 0) {
            return ApiResponseController::error('Supplier is missing email.');
        }

        // Generate PDF
        $pdfGenerator = new \App\Services\PdfGenerator();
        $pdfContent = $pdfGenerator->generateFromView('pdf.purchaseOrder', compact('purchaseOrder'));

        // Send email
        try {
            Mail::to($recipients)->queue(new \App\Mail\PurchaseOrder($purchaseOrder, $pdfContent));
        } catch (\Exception $e) {
            return ApiResponseController::error($e->getMessage());
        }

        // Should we also generate a new order for this supplier?
        if ($request->get('generate_new_order')) {
            Artisan::call('purchase-orders:generate', ['supplierID' => $purchaseOrder->supplier->id]);
        }

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
}
