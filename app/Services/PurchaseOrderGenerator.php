<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CurrencyConvertController;
use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Supplier;
use App\Services\VendoraAdmin\VendoraAdminTaskService;
use DateInterval;
use DatePeriod;
use DateTime;
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

        for ($i = 1;$i <= 12;$i++) {
            $weightManual = ConfigController::getConfig('purchase_system_weight_manual_' . $i, 0);
            $weightAuto = ConfigController::getConfig('purchase_system_weight_auto_' . $i, 1);

            $this->settings['weight_month_' . $i] = $weightManual ?: $weightAuto;
        }
    }

    /**
     * Generates a purchase order for all suppliers. Or a specific supplier if the supplierID is set.
     *
     * @param int $supplierID
     * @param int $isEmpty
     * @return void
     */
    public function generate(int $supplierID = 0, int $isEmpty = 0): void
    {
        $suppliers = collect();

        if ($supplierID) {
            $supplier = Supplier::find($supplierID);

            if ($supplier) {
                $suppliers->push($supplier);
            }
        }
        else {
            $suppliers = Supplier::where('purchase_system', '=', 1)->get();
        }

        foreach ($suppliers as $supplier) {
            $this->generateSupplierPurchaseOrder($supplier, $isEmpty);
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

        $lockedArticleNumbers = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->where('is_locked', 1)
            ->pluck('article_number')
            ->toArray();

        // Remove the existing order lines
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
            ->where('is_locked', 0)
            ->delete();

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
            $purchaseOrder->id,
            [],
            $lockedArticleNumbers
        );

        foreach ($orderLines as $orderLine) {
            PurchaseOrderLine::create($orderLine);
        }

        $purchaseOrder->update(['is_generating' => 0]);

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
     * @param int $isEmpty
     * @return bool
     */
    public function generateSupplierPurchaseOrder(Supplier $supplier, int $isEmpty): bool
    {
        $generatePurchaseOrder = true;

        // Check if the supplier has an open suggestion
        $hasOpenSuggestion = PurchaseOrder::where('supplier_id', '=', $supplier->external_id)
            ->where('status', '=', 'Draft')
            ->where('is_po_system', '=', 1)
            ->where('is_sent', '=', 0)
            ->where('is_draft', '=', 1)
            ->where('should_delete', '=', 0)
            ->exists();

        if ($isEmpty && $hasOpenSuggestion) {
            $generatePurchaseOrder = false;
        }

        if (!$hasOpenSuggestion && !$isEmpty) {
            // Check if days have passed to generate a new order
            $lastPurchaseOrderTime = $this->getSupplierLastOrder($supplier);

            if ($lastPurchaseOrderTime && strtotime($lastPurchaseOrderTime) >= strtotime('-' . $supplier->purchase_order_interval . ' day')) {
                $generatePurchaseOrder = false;
            }
        }

        if (!$generatePurchaseOrder) {
            return false;
        }

        $response = $this->createSupplierOrder($supplier, collect(), $isEmpty);

        return $response['success'];
    }

    /**
     * Generates a purchase order for a specific supplier.
     *
     * @param Supplier $supplier
     * @param Collection|null $vipSalesOrders
     * @param int $isEmpty
     * @return array
     */
    public function createSupplierOrder(Supplier $supplier, Collection $vipSalesOrders = null, int $isEmpty = 0): array
    {
        if ($vipSalesOrders === null) {
            $vipSalesOrders = collect();
        }

        // Check if we have a draft purchase order for this supplier
        $existingPurchaseOrder = PurchaseOrder::where('supplier_id', '=', $supplier->external_id)
            ->where('status', '=', 'Draft')
            ->where('is_po_system', '=', 1)
            ->where('is_sent', '=', 0)
            ->where('is_draft', '=', 1)
            ->where('should_delete', '=', 0)
            ->first();

        $excludeArticleNumbers = [];

        if ($existingPurchaseOrder) {
            // Remove existing non-locked order lines
            foreach ($existingPurchaseOrder->lines as $orderLine) {
                if ($orderLine->is_locked) {
                    $excludeArticleNumbers[] = $orderLine->article_number;
                } else {
                    $orderLine->delete();
                }
            }

            $existingPurchaseOrder->refresh();
        }


        if ($isEmpty) {
            $orderLines = collect();
        } else {
            // Collect all articles that need to be ordered
            $orderLines = $this->getOrderLines(
                $supplier,
                $vipSalesOrders,
                $this->settings['foresight_days'],
                0,
                [],
                $excludeArticleNumbers
            );

            if ($orderLines->isEmpty()) {
                return ['success' => false];
            }

            // Make sure minimum order quantity and value is met
            $totalQuantity = 0;
            $totalValue = 0;

            foreach ($orderLines as $orderLine) {
                $totalQuantity += $orderLine['quantity'];
                $totalValue += $orderLine['amount'];
            }

            if ($totalQuantity < $supplier->purchase_min_quantity || $totalValue < $supplier->purchase_min_value) {
                return ['success' => false];
            }
        }

        $isNewOrder = true;

        if ($existingPurchaseOrder) {
            // Merge the existing order lines with the new ones
            $isNewOrder = false;

            foreach ($orderLines as $orderLine) {
                foreach ($existingPurchaseOrder->lines as $purchaseOrderLine) {
                    if ($purchaseOrderLine->article_number != $orderLine['article_number']) {
                        continue;
                    }

                    $purchaseOrderLine->update([
                        'quantity' => $orderLine['quantity'],
                        'amount' => ($purchaseOrderLine->unit_cost * $orderLine['quantity']),
                    ]);

                    continue 2;
                }

                // Create a new order line if it doesn't exist
                $purchaseOrderLine['purchase_order_id'] = $existingPurchaseOrder->id;
                PurchaseOrderLine::create($orderLine);
            }

            $purchaseOrder = $existingPurchaseOrder;
        }
        else {
            // Create a new purchase order
            $newOrderNumber = PurchaseOrder::where('order_number', 'NOT LIKE', '%OLD-%')
                ->get()
                ->max('order_number');
            $newOrderNumber = intval($newOrderNumber) + 1;

            // Create the purchase order
            $purchaseOrder = PurchaseOrder::create([
                'order_number' => $newOrderNumber,
                'status' => 'Draft',
                'date' => date('Y-m-d'),
                'promised_date' => '',
                'supplier_id' => $supplier->external_id,
                'supplier_number' => $supplier->number,
                'supplier_name' => $supplier->name,
                'currency' => $supplier->currency,
                'amount' => 0,
                'is_draft' => 1,
                'is_vip' => ($vipSalesOrders->count() > 0),
                'foresight_days' => $this->settings['foresight_days'],
                'email' => $supplier->supplier_contact_email ?: $supplier->email,
                'is_po_system' => 1,
                'currency_rate' => round((new CurrencyConvertController)->convert(1, $supplier->currency, 'SEK'), 2),
            ]);

            foreach ($orderLines as $orderLine) {
                $orderLine['purchase_order_id'] = $purchaseOrder->id;

                PurchaseOrderLine::create($orderLine);
            }
        }

        $purchaseOrder->refresh();

        // Update the total amount of the purchase order
        $totalAmount = $purchaseOrder->lines->sum(function ($line) {
            return $line->unit_cost * $line->quantity;
        });

        $purchaseOrder->update([
            'amount' => $totalAmount,
        ]);

        // Create a task
        if ($isNewOrder) {
            $taskService = new VendoraAdminTaskService();
            $taskID = $taskService->createTask('purchase_order', [
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_name' => $supplier->name,
            ]);
        }

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
     * @param array $allowedArticleNumbers
     * $param array $excludeArticleNumbers
     * @return Collection
     */
    private function getOrderLines(Supplier $supplier, Collection $vipSalesOrders, int $foresightDays, array $allowedArticleNumbers = [], array $excludeArticleNumbers = [])
    {
        $articles = Article::where('supplier_number', $supplier->number)
            ->where('status', 'Active')
            ->get();

        if (!$articles->count()) {
            return collect();
        }

        $orderLines = collect();

        $lineKey = 0;

        $supplierPriceService = new SupplierArticlePriceService();

        $excludeArticles = array_merge($this->excludeArticles, $excludeArticleNumbers);

        foreach ($articles as $article) {
            // Exclude articles
            if (in_array($article->article_number, $excludeArticles)) {
                continue;
            }

            if (count($allowedArticleNumbers) && !in_array($article->article_number, $allowedArticleNumbers)) {
                continue;
            }

            list($quantity, $aiComment) = $this->getQuantityToOrder($article, $vipSalesOrders, $foresightDays);

            if (!$quantity['quantity']) {
                continue;
            }

            // Calculate the unit purchase price
            $unitCost = $supplierPriceService->getUnitCostForSupplier($article->article_number, $supplier);
            $unitCost = round($unitCost, 2);

            $orderLines->push([
                'purchase_order_id' => $purchaseOrderID,
                'line_key' => $lineKey++,
                'article_number' => $article->article_number,
                'description' => $article->description,
                'quantity' => $quantity['quantity'],
                'suggested_quantity' => $quantity['default'],
                'suggested_quantity_master' => $quantity['master'],
                'suggested_quantity_inner' => $quantity['inner'],
                'suggested_quantity_month' => $quantity['month_default'],
                'suggested_quantity_month_master' => $quantity['month_master'],
                'suggested_quantity_month_inner' => $quantity['month_inner'],
                'unit_cost' => $unitCost,
                'amount' => ($unitCost * $quantity['default']),
                'is_vip' => $this->isVIPArticle($vipSalesOrders, $article->article_number),
                'ai_comment' => $aiComment,
                'promised_date' => '',
            ]);
        }

        return $orderLines;
    }

    public function getQuantityToOrderV2(Article $article, int $foresightDays): array
    {
        // Fetch legacy article if it exists
        $legacyArticle = null;
        if ($article->predecessor) {
            $legacyArticleQuery = Article::where('article_number', $article->predecessor);

            if ($legacyArticleQuery->exists()) {
                $legacyArticle = $legacyArticle->first();
            }
        }


    }

    /**
     * Returns the quantity to order for a specific article.
     *
     * @param Article $article
     * @param Collection $vipSalesOrders
     * @param int $foresightDays
     * @return array
     */
    public function getQuantityToOrder(Article $article, Collection $vipSalesOrders, int $foresightDays): array
    {
        $hasPurchaseOrders = PurchaseOrderLine::where('article_number', $article->article_number)->exists();

        $periods = [
            'last_7_days' => [
                'sales_volume' => $article->sales_7_days / 7,
                'weight' => $this->settings['last_7_days_weight']
            ],
            'last_30_days' => [
                'sales_volume' => $article->sales_30_days / 30,
                'weight' => $this->settings['last_30_days_weight']
            ],
            'last_90_days' => [
                'sales_volume' => $article->sales_90_days / 90,
                'weight' => $this->settings['last_90_days_weight']
            ],
            'last_year' => [
                'sales_volume' => $article->sales_last_year / 30,
                'weight' => $this->settings['last_year_weight']
            ],
        ];

        // Calculate the average sales volume for the periods and weight them against the weight value
        //$suggestedStock = ArticleQuantityCalculator::getOnOrder($article->article_number);
        $suggestedStock = ($periods['last_7_days']['sales_volume'] * $periods['last_7_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_30_days']['sales_volume'] * $periods['last_30_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_90_days']['sales_volume'] * $periods['last_90_days']['weight'] * $foresightDays);
        $suggestedStock += ($periods['last_year']['sales_volume'] * $periods['last_year']['weight'] * $foresightDays);

        // Weigh against the month values
        $weightMonth = $this->mostOccurringMonth(
            date('Y-m-d'),
            date('Y-m-d', strtotime('+' . $foresightDays . ' days'))
        );

        $suggestedMonthStock = $suggestedStock * $this->settings['weight_month_' . $weightMonth];

        // Add the VIP orders to the suggested stock
        /*foreach ($vipSalesOrders as $line) {
            if ($line->article_number != $article->article_number) {
                continue;
            }

            $suggestedStock += (int) $line->quantity;
            $suggestedMonthStock += (int) $line->quantity;
        }*/


        // Sum up the current stock
        $currentStock = ArticleQuantityCalculator::getNetStock($article->article_number);

        // Calculate box sizes
        $innerBoxQuantity = max(1, $article->inner_box);
        $masterBoxQuantity = max(1, $article->master_box) * $innerBoxQuantity;

        // Calculate exact suggestion
        $quantity = $suggestedStock - $currentStock;
        $quantityMonth = $suggestedMonthStock - $currentStock;


        // This is the quantity that is suggested to order at the moment
        $quantityToOrder = [
            'quantity' => $quantity,
            'default' => $quantity,
            'master' => ceil($quantity / $masterBoxQuantity) * $masterBoxQuantity,
            'inner' => ceil($quantity / $innerBoxQuantity) * $innerBoxQuantity,
            'month_default' => $quantityMonth,
            'month_master' => ceil($quantityMonth / $masterBoxQuantity) * $masterBoxQuantity,
            'month_inner' => ceil($quantityMonth / $innerBoxQuantity) * $innerBoxQuantity,
        ];


        // Check if this is a new article that have never been ordered before
        $isNewArticle = !$hasPurchaseOrders && !$currentStock;


        if ($isNewArticle) {
            // Always buy 1 master box if the article is new
            $quantityToOrder = [
                'quantity' => $masterBoxQuantity ?: 1,
                'default' => $masterBoxQuantity ?: 1,
                'master' => $masterBoxQuantity ?: 1,
                'inner' => $innerBoxQuantity ?: 1,
                'month_default' => $masterBoxQuantity ?: 1,
                'month_master' => $masterBoxQuantity ?: 1,
                'month_inner' => $innerBoxQuantity ?: 1,
            ];
        }
        elseif ($article->supplier->purchase_master_box ?? false) {
            // Use the master box as default quantity
            $quantityToOrder['quantity'] = $quantityToOrder['master'];
        }
        elseif ($article->supplier->purchase_inner_box ?? false) {
            // Use the inner box as default quantity
            $quantityToOrder['quantity'] = $quantityToOrder['inner'];
        }

        // Make sure no values are negative
        $quantityToOrder['quantity'] = max(0, $quantityToOrder['quantity']);
        $quantityToOrder['default'] = max(0, $quantityToOrder['default']);
        $quantityToOrder['master'] = max(0, $quantityToOrder['master']);
        $quantityToOrder['inner'] = max(0, $quantityToOrder['inner']);
        $quantityToOrder['month_default'] = max(0, $quantityToOrder['month_default']);
        $quantityToOrder['month_master'] = max(0, $quantityToOrder['month_master']);
        $quantityToOrder['month_inner'] = max(0, $quantityToOrder['month_inner']);

        // Motivate the quantity
        /*$motivator = new PurchaseOrderMotivator();
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
            'vip_quantity' => $vipQuantity,
            'use_master_box' => $useMasterBox,
            'master_box' => $masterBoxQuantity,
            'is_new_article' => $isNewArticle,
        ]);*/

        return [$quantityToOrder, ''];
    }

    /**
     * Return all VIP orders for a specific supplier within a time frame
     *
     * @param Supplier $supplier
     * @return \Illuminate\Support\Collection
     */
    private function getVIPSalesOrders(Supplier $supplier, string $startDate, string $endDate)
    {
        return DB::table('sales_order_lines')
            ->select('sales_order_lines.*')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
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
        foreach ($vipSalesOrders as $line) {
            if ($line->article_number == $articleNumber) {
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
        $lastPurchaseOrder = PurchaseOrder::where('supplier_id', $supplier->external_id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastPurchaseOrder) {
            return null;
        }

        return $lastPurchaseOrder->created_at;
    }

    /**
     * Returns the month with the most days in a date range
     * @param string $startDate
     * @param string $endDate
     * @return int
     * @throws \Exception
     */
    private function mostOccurringMonth(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day'); //  the end date in the interval

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($start, $interval, $end);

        $monthCount = [];

        foreach ($period as $date) {
            $month = $date->format('m');
            if (!isset($monthCount[$month])) {
                $monthCount[$month] = 0;
            }
            $monthCount[$month]++;
        }

        arsort($monthCount); // Sort the array in descending order of days count

        return array_key_first($monthCount); // Return the month with the most days
    }
}
