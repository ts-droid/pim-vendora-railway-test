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
        if ($purchaseOrder->is_draft) {
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
            ($purchaseOrder->supplier->last_purchase_order_run ?: date('Y-m-d H:i:s', strtotime('-7 days'))),
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

        $purchaseOrderMotivator = new PurchaseOrderMotivator();
        $purchaseOrderMotivator->motivateQuantity($purchaseOrder);

        // Set timestamp for last order generation
        $purchaseOrder->supplier->update(['last_purchase_order_run' => date('Y-m-d H:i:s')]);

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

        // Check if the supplier has any "VIP orders"
        $vipSalesOrders = $this->getVIPSalesOrders(
            $supplier,
            ($supplier->last_purchase_order_run ?: date('Y-m-d H:i:s', strtotime('-7 days'))),
            date('Y-m-d H:i:s')
        );

        if ($vipSalesOrders->count()) {
            $generatePurchaseOrder = true;
        }

        // Check when last we last tries to generate an order for this supplier
        if (!$supplier->last_purchase_order_run
            || strtotime($supplier->last_purchase_order_run) < strtotime('-' . $supplier->purchase_order_interval . ' day')
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

        $purchaseOrderMotivator = new PurchaseOrderMotivator();
        $purchaseOrderMotivator->motivateQuantity($purchaseOrder);

        // Create a task
        $taskService = new VendoraAdminTaskService();
        $taskID = $taskService->createTask('purchase_order', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_name' => $supplier->name,
        ]);

        // Set timestamp for last order generation
        $supplier->update(['last_purchase_order_run' => date('Y-m-d H:i:s')]);

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
        $articles = Article::where('supplier_number', $supplier->number)->get();

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

            $quantity = $this->getQuantityToOrder($article, $vipSalesOrders, $foresightDays);

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
                'unit_cost' => 0,
                'amount' => 0,
                'is_vip' => $this->isVIPArticle($vipSalesOrders, $article->article_number),
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
     * @return int
     */
    private function getQuantityToOrder(Article $article, Collection $vipSalesOrders, int $foresightDays): int
    {
        $salesVolumeCalculator = new SalesVolumeCalculator();

        $periods = [
            'last_7_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $article->article_number, $this->settings['last_7_days_weight'], '-7 days'),
            'last_30_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $article->article_number, $this->settings['last_30_days_weight'], '-30 days'),
            'last_90_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $article->article_number, $this->settings['last_90_days_weight'], '-90 days'),
            'last_year' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $article->article_number, $this->settings['last_year_weight'], '-365 days', '-335 days'),
        ];

        // Calculate the average sales volume for the periods and weight them against the weight value
        $suggestedStock = ($periods['last_7_days']['sales_volume']['average'] * $periods['last_7_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_30_days']['sales_volume']['average'] * $periods['last_30_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_90_days']['sales_volume']['average'] * $periods['last_90_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_year']['sales_volume']['average'] * $periods['last_year']['weight'] * $foresightDays);


        // Add the VIP orders to the suggested stock
        foreach ($vipSalesOrders as $salesOrder) {
            $suggestedStock += (int) $salesOrder->order_total_quantity;
        }


        // Sum up the current stock
        $currentStock = $article->stock;

        // Calculate how many items are on their way
        $incomingQuantity = ArticleQuantityCalculator::getIncoming($article->article_number);

        // This is the quantity that is suggested to order at the moment
        $quantityToOrder = $suggestedStock - $currentStock - $incomingQuantity;

        // Round to the closest master box size
        if ($article->supplier->purchase_master_box && $article->master_box) {
            $quantityToOrder = round($quantityToOrder / $article->master_box) * $article->master_box;
        }

        return max(0, $quantityToOrder);
    }

    /**
     * Returns the sales volume and weight for a specific period.
     *
     * @param SalesVolumeCalculator $calculator
     * @param string $articleNumber
     * @param float $weight
     * @param string $start
     * @param string $end
     * @return array
     */
    private function getSalesVolumeAndWeight(SalesVolumeCalculator $calculator, string $articleNumber, float $weight, string $start, string $end = ''): array
    {
        $startDate = date('Y-m-d', strtotime($start));
        $endDate = $end ? date('Y-m-d', strtotime($end)) : date('Y-m-d');

        return [
            'sales_volume' => $calculator->calculateSalesVolume($articleNumber, $startDate, $endDate),
            'weight' => $weight,
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
            ->where('sales_orders.created_at', '>=', $startDate)
            ->where('sales_orders.created_at', '<=', $endDate)
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

    // Questions:
    // - Ska vi skicka med något pris till Visma.net? (Skicka med kostnader på ordern)
    // - Om en inköpsorder skapas, adderas det på lagersaldot eller görs det när den kommer in? (lager räknas upp när order kommer in)
}
