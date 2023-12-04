<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Supplier;
use App\Services\VendoraAdmin\VendoraAdminTaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderGenerator
{
    protected array $settings;

    protected array $excludeArticles = [
        'SHIP25'
    ];

    /**
     * PurchaseOrderGenerator constructor.
     */
    public function __construct()
    {
        $this->settings = [
            'last_7_days_weight' => ConfigController::getConfig('purchase_system_7_day_weight', 0.2),
            'last_30_days_weight' => ConfigController::getConfig('purchase_system_30_day_weight', 0.3),
            'last_90_days_weight' => ConfigController::getConfig('purchase_system_90_day_weight', 0.35),
            'last_year_weight' => ConfigController::getConfig('purchase_system_year_weight', 0.15),
            'vip_order_quantity_limit' => ConfigController::getConfig('purchase_system_vip_quantity', 500),
            'vip_order_value_limit' => ConfigController::getConfig('purchase_system_vip_value', 100_000),
            'foresight_days' => ConfigController::getConfig('purchase_system_foresight_days', 14),
        ];
    }

    /**
     * Generates a purchase order for all suppliers. Or a specific supplier if the supplierID is set.
     *
     * @param int $supplierID
     * @return void
     */
    public function generate(int $supplierID = 0): void
    {
        $suppliers = collect();

        if ($supplierID) {
            $supplier = Supplier::find($supplierID);

            if ($supplier) {
                $suppliers->push($supplier);
            }
        }
        else {
            $suppliers = Supplier::all();
        }

        foreach ($suppliers as $supplier) {
            $orderCreated = $this->generateSupplierPurchaseOrder($supplier);

            // TODO: Remove this when we going to production
            if ($orderCreated) {
                return;
            }
        }
    }

    /**
     * Regenerates a purchase order (Only if it's a draft)
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function regenerate(PurchaseOrder $purchaseOrder): array
    {
        if (!$purchaseOrder->is_draft) {
            return [
                'success' => false,
                'message' => 'The purchase order is not a draft.',
            ];
        }

        // Remove the existing order lines
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->delete();

        // Add new order lines
        $vipSalesOrders = $this->getVIPSalesOrders(
            $purchaseOrder->supplier,
            ($this->getSupplierLastOrder($purchaseOrder->supplier) ?: date('Y-m-d H:i:s', strtotime('-7 days'))),
            date('Y-m-d H:i:s')
        );

        $orderLines = $this->getOrderLines(
            $purchaseOrder->supplier,
            $vipSalesOrders,
            $purchaseOrder->foresight_days,
            $purchaseOrder->id
        );

        foreach ($orderLines as $orderLine) {
            PurchaseOrderLine::create($orderLine);
        }

        $purchaseOrder->refresh();

        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * Generates a purchase order for a specific supplier.
     * Returns true if an order was generated, false if not.
     *
     * @param Supplier $supplier
     * @return bool
     */
    public function generateSupplierPurchaseOrder(Supplier $supplier): bool
    {
        $generatePurchaseOrder = false;

        $lastPurchaseOrderTime = $this->getSupplierLastOrder($supplier);

        // Check if the supplier has any "VIP orders"
        $vipSalesOrders = $this->getVIPSalesOrders(
            $supplier,
            ($lastPurchaseOrderTime ?: date('Y-m-d H:i:s', strtotime('-7 days'))),
            date('Y-m-d H:i:s')
        );

        if ($vipSalesOrders->count()) {
            $generatePurchaseOrder = true;
        }

        // Check when last we last tries to generate an order for this supplier
        if (!$lastPurchaseOrderTime
            || strtotime($lastPurchaseOrderTime) < strtotime('-' . $supplier->purchase_order_interval . ' day')
        ) {
            $generatePurchaseOrder = true;
        }

        if (!$generatePurchaseOrder) {
            return false;
        }

        $response = $this->createSupplierOrder($supplier, $vipSalesOrders);

        return $response['success'];
    }

    /**
     * Generates a purchase order for a specific supplier.
     *
     * @param Supplier $supplier
     * @return array
     */
    public function createSupplierOrder(Supplier $supplier, Collection $vipSalesOrders = null): array
    {
        if ($vipSalesOrders === null) {
            $vipSalesOrders = collect();
        }

        // Collect all articles that need to be ordered
        $orderLines = $this->getOrderLines(
            $supplier,
            $vipSalesOrders,
            $this->settings['foresight_days']
        );

        if ($orderLines->isEmpty()) {
            return ['success' => false];
        }

        // Create the purchase order
        $purchaseOrder = PurchaseOrder::create([
            'order_number' => 'DRFT-' . $supplier->id . '-' . date('YmdHis'),
            'status' => 'Draft',
            'date' => date('Y-m-d'),
            'promised_date' => '',
            'supplier_id' => $supplier->id,
            'supplier_number' => $supplier->number,
            'supplier_name' => $supplier->name,
            'currency' => $supplier->currency,
            'amount' => 0,
            'is_draft' => 1,
            'is_vip' => ($vipSalesOrders->count() > 0),
            'foresight_days' => $this->settings['foresight_days'],
        ]);

        foreach ($orderLines as $orderLine) {
            $orderLine['purchase_order_id'] = $purchaseOrder->id;

            PurchaseOrderLine::create($orderLine);
        }

        $purchaseOrder->refresh();

        // Create a task
        $taskService = new VendoraAdminTaskService();
        $taskID = $taskService->createTask('purchase_order', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_name' => $supplier->name,
        ]);

        return [
            'success' => true,
            'purchase_order_id' => $purchaseOrder->id,
            'task' => null,
        ];
    }

    /**
     * Returns all order lines for a purchase order
     *
     * @param Supplier $supplier
     * @param Collection $vipSalesOrders
     * @param int $foresightDays
     * @param int $purchaseOrderID
     * @return Collection
     */
    private function getOrderLines(Supplier $supplier, Collection $vipSalesOrders, int $foresightDays, int $purchaseOrderID = 0)
    {
        $articles = Article::where('supplier_number', $supplier->number)
            ->where('status', 'Active')
            ->get();

        if (!$articles->count()) {
            return collect();
        }

        $orderLines = collect();

        $lineKey = 0;

        foreach ($articles as $article) {
            // Exclude articles
            if (in_array($article->article_number, $this->excludeArticles)) {
                continue;
            }

            list($quantity, $aiComment) = $this->getQuantityToOrder($article, $vipSalesOrders, $foresightDays);

            if (!$quantity) {
                continue;
            }

            $orderLines->push([
                'purchase_order_id' => $purchaseOrderID,
                'line_key' => $lineKey++,
                'article_number' => $article->article_number,
                'description' => $article->description,
                'quantity' => $quantity,
                'suggested_quantity' => $quantity,
                'unit_cost' => $article->external_cost,
                'amount' => ($article->external_cost * $quantity),
                'is_vip' => $this->isVIPArticle($vipSalesOrders, $article->article_number),
                'ai_comment' => $aiComment,
                'promised_date' => '',
            ]);
        }

        return $orderLines;
    }

    /**
     * Returns the quantity to order for a specific article.
     *
     * @param Article $article
     * @param Collection $vipSalesOrders
     * @param int $foresightDays
     * @return array
     */
    private function getQuantityToOrder(Article $article, Collection $vipSalesOrders, int $foresightDays): array
    {
        $salesVolumeCalculator = new SalesVolumeCalculator();

        $isNewArticle = !PurchaseOrderLine::where('article_number', $article->article_number)->exists();

        $periods = [
            'last_7_days' => [
                'sales_volume' => $article->sales_7_days,
                'weight' => $this->settings['last_7_days_weight']
            ],
            'last_30_days' => [
                'sales_volume' => $article->sales_30_days,
                'weight' => $this->settings['last_30_days_weight']
            ],
            'last_90_days' => [
                'sales_volume' => $article->sales_90_days,
                'weight' => $this->settings['last_90_days_weight']
            ],
            'last_year' => [
                'sales_volume' => $article->sales_last_year,
                'weight' => $this->settings['last_year_weight']
            ],
        ];

        // Calculate the average sales volume for the periods and weight them against the weight value
        $suggestedStock = ($periods['last_7_days']['sales_volume'] * $periods['last_7_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_30_days']['sales_volume'] * $periods['last_30_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_90_days']['sales_volume'] * $periods['last_90_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_year']['sales_volume'] * $periods['last_year']['weight'] * $foresightDays);


        // Add the VIP orders to the suggested stock
        $vipQuantity = 0;

        foreach ($vipSalesOrders as $salesOrder) {
            $suggestedStock += (int) $salesOrder->order_total_quantity;
            $vipQuantity += (int) $salesOrder->order_total_quantity;
        }


        // Sum up the current stock
        $currentStock = $article->stock;

        // Calculate how many items are on their way
        $incomingQuantity = ArticleQuantityCalculator::getIncoming($article->article_number);

        // This is the quantity that is suggested to order at the moment
        $quantityToOrder = $suggestedStock - $currentStock - $incomingQuantity;

        // Round to the closest master box size
        $masterBoxQuantity = $article->master_box * $article->inner_box;

        $useMasterBox = ($article->supplier->purchase_master_box && $masterBoxQuantity);

        if ($useMasterBox) {
            $quantityToOrder = round($quantityToOrder / $masterBoxQuantity) * $masterBoxQuantity;
        }

        if ($isNewArticle) {
            $quantityToOrder = $article->master_box ?: 1;
        }

        // Motivate the quantity
        $motivator = new PurchaseOrderMotivator();
        $aiComment = $motivator->motivateQuantity([
            'foresight_days' => $foresightDays,
            'sales_last_7_days' => $periods['last_7_days']['sales_volume'],
            'sales_last_30_days' => $periods['last_30_days']['sales_volume'],
            'sales_last_90_days' => $periods['last_90_days']['sales_volume'],
            'sales_last_year' => $periods['last_year']['sales_volume'],
            'weight_7_days' => $periods['last_7_days']['weight'],
            'weight_30_days' => $periods['last_30_days']['weight'],
            'weight_90_days' => $periods['last_90_days']['weight'],
            'weight_year' => $periods['last_year']['weight'],
            'current_stock' => $currentStock,
            'incoming_stock' => $incomingQuantity,
            'vip_quantity' => $vipQuantity,
            'use_master_box' => $useMasterBox,
            'master_box' => $masterBoxQuantity,
            'is_new_article' => $isNewArticle,
        ]);

        return [
            max(0, $quantityToOrder),
            $aiComment
        ];
    }

    /**
     * Return all VIP orders for a specific supplier within a time frame
     *
     * @param Supplier $supplier
     * @return \Illuminate\Support\Collection
     */
    private function getVIPSalesOrders(Supplier $supplier, string $startDate, string $endDate)
    {
        return DB::table('sales_orders')
            ->select('sales_orders.*')
            ->distinct()
            ->join('sales_order_lines', 'sales_order_lines.sales_order_id', '=', 'sales_orders.id')
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->where('sales_orders.date', '>=', $startDate)
            ->where('sales_orders.date', '<=', $endDate)
            ->where('articles.supplier_number', $supplier->number)
            ->where(DB::raw('sales_orders.order_total * sales_orders.exchange_rate'), '>=', $this->settings['vip_order_value_limit'])
            ->where('sales_orders.order_total_quantity', '>=', $this->settings['vip_order_quantity_limit'])
            ->get();
    }

    /**
     * Returns true if an article is on a VIP order
     *
     * @param Collection $vipSalesOrders
     * @param string $articleNumber
     * @return bool
     */
    private function isVIPArticle(Collection $vipSalesOrders, string $articleNumber): bool
    {
        foreach ($vipSalesOrders as $salesOrder) {
            $hasArticleOnOrder = SalesOrderLine::where('sales_order_id', $salesOrder->id)
                ->where('article_number', $articleNumber)
                ->exists();

            if ($hasArticleOnOrder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the last purchase order date for a specific supplier
     *
     * @param Supplier $supplier
     * @return string|null
     */
    private function getSupplierLastOrder(Supplier $supplier): ?string
    {
        $lastPurchaseOrder = PurchaseOrder::where('supplier_id', $supplier->id)
            ->orderBy('date', 'desc')
            ->first();

        if (!$lastPurchaseOrder) {
            return null;
        }

        return $lastPurchaseOrder->date;
    }
}
