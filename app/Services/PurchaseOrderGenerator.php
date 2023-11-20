<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Services\VendoraAdmin\VendoraAdminTaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderGenerator
{
    protected array $settings;

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
     * Generates a purchase order for all suppliers.
     *
     * @return void
     */
    public function generateAll(): void
    {
        $suppliers = Supplier::all();

        foreach ($suppliers as $supplier) {

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
                continue;
            }

            $this->createSupplierOrder($supplier, $vipSalesOrders);
        }
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

        // Fetch all articles for the supplier
        $articles = Article::where('supplier_number', $supplier->number)->get();

        if (!$articles->count()) {
            return ['success' => false];
        }

        // Collect all articles that need to be ordered
        $orderLines = collect();

        $lineKey = 0;

        foreach ($articles as $article) {
            $quantityResponse = $this->getQuantityToOrder($article, $vipSalesOrders);

            if (!$quantityResponse['quantity']) {
                continue;
            }

            $orderLines->push([
                'line_key' => $lineKey++,
                'article_number' => $article->article_number,
                'description' => $article->description,
                'quantity' => $quantityResponse['quantity'],
                'unit_cost' => 0,
                'amount' => 0,
                'promised_date' => '',
            ]);
        }

        if ($orderLines->isEmpty()) {
            return ['success' => false];
        }

        // Create the purchase order
        $purchaseOrder = PurchaseOrder::create([
            'order_number' => 'DRFT-' . $supplier->id . '-' . date('YmdHis'),
            'status' => 'draft',
            'date' => date('Y-m-d'),
            'promised_date' => '',
            'supplier_id' => $supplier->id,
            'supplier_number' => $supplier->number,
            'supplier_name' => $supplier->name,
            'currency' => $supplier->currency,
            'amount' => 0,
            'is_draft' => 1,
            'is_vip' => ($vipSalesOrders->count() > 0),
        ]);

        foreach ($orderLines as $orderLine) {
            $orderLine['purchase_order_id'] = $purchaseOrder->id;

            PurchaseOrderLine::create($orderLine);
        }

        // Create a task
        $taskService = new VendoraAdminTaskService();
        $taskService->createTask('purchase_order', [
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
     * Returns the quantity to order for a specific article.
     *
     * @param Article $article
     * @return array
     */
    private function getQuantityToOrder(Article $article, Collection $vipSalesOrders): array
    {
        $salesVolumeCalculator = new SalesVolumeCalculator();

        $periods = [
            'last_7_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $this->settings['last_7_days_weight'], '-7 days'),
            'last_30_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $this->settings['last_30_days_weight'], '-30 days'),
            'last_90_days' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $this->settings['last_90_days_weight'], '-90 days'),
            'last_year' => $this->getSalesVolumeAndWeight($salesVolumeCalculator, $this->settings['last_year_weight'], '-365 days', '-335 days'),
        ];

        // TODO: Add support to also set this value per supplier
        $foresightDays = $this->settings['foresight_days'];

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
        $incomingQuantity = $this->getIncomingQuantity($article->article_number);

        // This is the quantity that is suggested to order at the moment
        $quantityToOrder = $suggestedStock - $currentStock - $incomingQuantity;

        // Round to the closest master box size
        if ($article->supplier->purchase_master_box && $article->master_box) {
            $quantityToOrder = round($quantityToOrder / $article->master_box) * $article->master_box;
        }

        return [
            'quantity' => max(0, $quantityToOrder),
            'analysis' => 'Add AI comment/motivation here...', // TODO: Add AI comment/motivation here
        ];
    }

    /**
     * Returns the sales volume and weight for a specific period.
     *
     * @param SalesVolumeCalculator $calculator
     * @param float $weight
     * @param string $start
     * @param string $end
     * @return array
     */
    private function getSalesVolumeAndWeight(SalesVolumeCalculator $calculator, float $weight, string $start, string $end = ''): array
    {
        $startDate = date('Y-m-d', strtotime($start));
        $endDate = $end ? date('Y-m-d', strtotime($end)) : date('Y-m-d');

        return [
            'sales_volume' => $calculator->calculateSalesVolume($startDate, $endDate),
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
            ->join('articles', 'articles.article_number', '=', 'sales_order_lines.article_number')
            ->where('sales_orders.created_at', '>=', $startDate)
            ->where('sales_orders.created_at', '<=', $endDate)
            ->where('articles.supplier_number', $supplier->number)
            ->where(DB::raw('sales_orders.order_total * sales_orders.exchange_rate'), '>=', $this->settings['vip_order_value_limit'])
            ->where('sales_orders.order_total_quantity', '>=', $this->settings['vip_order_quantity_limit'])
            ->get();
    }

    /**
     * Returns the number of incoming quantities for a specific article.
     *
     * @param string $articleNumber
     * @return int
     */
    private function getIncomingQuantity(string $articleNumber): int
    {
        return DB::table('purchase_order_lines')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_order_lines.article_number', $articleNumber)
            ->where('purchase_orders.status', '!=', 'Closed')
            ->sum('quantity');
    }






    // Questions:
    // - Ska vi skicka med något pris till Visma.net? (Skicka med kostnader på ordern)
    // - Om en inköpsorder skapas, adderas det på lagersaldot eller görs det när den kommer in? (lager räknas upp när order kommer in)






    // Limits for VIP orders (quantity and value)
    // Flag on purchase orders for VIP orders
    // Work with master-box quantity (setting on supplier, use master or not) (exception handling if master-quatity is missing)

    // Generate PDF that is sent to supplier (supplier => Enter prefered shipping agent)
    // Link on PDF to confirm order => Enter ETA => Send to Visma.net

    // Setting per supplier how often in days to generate orders (if case is not emergency)

    // Check against:
    // - Sales now (last 7 days)
    // - Sales last month (last 30 days)
    // - Sales last quarter (last 90 days)
    // - Sales this period last year (date - +30 days)
    // - Stlrata kunder
    // - Livscykel. när fick vi produkten och hur ser utvevcklingen ut

    // If AI decides this, also add a motivation as to why
}
