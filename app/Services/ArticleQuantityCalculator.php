<?php

namespace App\Services;

use App\Models\Article;
use App\Services\WGR\WGROrderQueueService;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ArticleQuantityCalculator
{
    public static function getIncomingByDate(string $articleNumber): array
    {
        $incomingByDate = self::getIncomingByDateQuantities();

        return $incomingByDate[$articleNumber] ?? [];
    }

    public static function getIncomingByDateQuantities(): array
    {
        // Try to get the results from the cache
        $incomingByDateQuantities = Cache::get('incoming_by_date');

        // If the results are not in the cache
        if ($incomingByDateQuantities === null) {
            $incomingByDateQuantities = DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                ->whereIn('purchase_orders.status', ['Open', 'Hold', 'Draft'])
                ->where('purchase_orders.is_draft', '=', 0)
                ->where('purchase_order_lines.is_completed', '=' ,0)
                ->where('purchase_order_lines.is_canceled', '=' ,0)
                ->select('purchase_order_lines.article_number', 'purchase_orders.order_number', 'purchase_orders.date', DB::raw('SUM(quantity - quantity_received) as quantity'))
                ->groupBy('purchase_order_lines.article_number', 'purchase_orders.order_number', 'purchase_orders.date')
                ->get()
                ->groupBy('article_number')
                ->map(function ($dateGroup) {
                    return collect($dateGroup)->mapWithKeys(function ($row) {
                        // Reformat the date
                        $date = (new DateTime($row->date))->format('Y-m-d');

                        return [$date => [$row->quantity . ' pcs', $row->order_number]];
                    });
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('incoming_by_date', $incomingByDateQuantities, 10);
        }

        return $incomingByDateQuantities;
    }

    /**
     * Returns the number of incoming articles
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getIncoming(string $articleNumber): int
    {
        $incomingQuantities = self::getIncomingQuantities();

        return $incomingQuantities[$articleNumber] ?? 0;
    }

    public static function getIncomingQuantities(): array
    {
        // Try to get the results from the cache
        $incomingQuantities = Cache::get('incoming_quantities');

        // If the results are not in the cache
        if ($incomingQuantities === null) {
            // Fetch the results from the database
            $incomingQuantities = DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                ->whereIn('purchase_orders.status', ['Open', 'Hold', 'Draft'])
                ->where('purchase_orders.is_draft', '=', 0)
                ->where('purchase_order_lines.is_completed', '=' ,0)
                ->where('purchase_order_lines.is_canceled', '=' ,0)
                ->select('purchase_order_lines.article_number', DB::raw('SUM(quantity - quantity_received) as total_quantity'))
                ->groupBy('purchase_order_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) {
                    return $row->total_quantity;
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('incoming_quantities', $incomingQuantities, 10);
        }

        return $incomingQuantities;
    }

    public static function getOnOrderByDate(string $articleNumber): array
    {
        $onOrderByDate = self::getOnOrderByDateQuantities();

        return $onOrderByDate[$articleNumber] ?? [];
    }

    public static function getOnOrderByDateQuantities(): array
    {
        // Try to get the results from the cache
        $onOrderByDateQuantities = Cache::get('on_order_by_date');

        // If the results are not in the cache
        if ($onOrderByDateQuantities === null) {

            // Fetch data from WGR order hold queue
            //$WGRService = new WGROrderQueueService();
            //$onOrderByDateQuantities = $WGRService->getQuantityInQueueByDate();

            $onOrderByDateQuantities = [];

            // Fetch normal sales orders
            $orderLines = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->join('customers', 'customers.external_id', '=', 'sales_orders.customer')
                ->select(
                    'sales_order_lines.article_number', 'sales_order_lines.quantity_open',
                    'sales_orders.date', 'customers.name'
                )
                ->where('sales_order_lines.is_completed', '=', 0)
                ->whereIn('sales_orders.status', ['Open', 'BackOrder', 'Hold'])
                ->whereIn('sales_orders.type', ['WO', 'SO'])
                ->get()
                ->toArray();

            $orderLines = json_decode(json_encode($orderLines), true);

            if ($orderLines) {
                foreach ($orderLines as $orderLine) {
                    if (!isset($onOrderByDateQuantities[$orderLine['article_number']])) {
                        $onOrderByDateQuantities[$orderLine['article_number']] = [];
                    }

                    $onOrderByDateQuantities[$orderLine['article_number']][] = date('Y-m-d', strtotime($orderLine['date'])) . ' - ' . $orderLine['name'] . ' - ' . $orderLine['quantity_open'] . 'pcs';
                }
            }


            // Store the results in the cache for 10 minutes
            Cache::put('on_order_by_date', $onOrderByDateQuantities, 10);
        }

        return $onOrderByDateQuantities;
    }

    /**
     * Returns the number of items on active sales orders
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getOnOrder(string $articleNumber): int
    {
        $onOrderQuantities = self::getOnOrderQuantities();

        return $onOrderQuantities[$articleNumber] ?? 0;
    }

    public static function getOnOrderQuantities(): array
    {
        // Try to get the results from the cache
        $onOrderQuantities = Cache::get('on_order_quantities');

        // If the results are not in the cache
        if ($onOrderQuantities === null) {
            // Fetch the results from the database
            $onOrderQuantities = DB::table('sales_order_lines')
                ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                ->where('sales_order_lines.is_completed', '=', 0)
                ->whereIn('sales_orders.type', ['WO', 'SO'])
                ->whereIn('sales_orders.status', ['Open', 'BackOrder', 'Hold'])
                ->select('sales_order_lines.article_number', DB::raw('SUM(quantity_open) as total_quantity'))
                ->groupBy('sales_order_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) {
                    return $row->total_quantity;
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('on_order_quantities', $onOrderQuantities, 10);
        }

        return $onOrderQuantities;
    }

    /**
     * Returns the quantity in hold queue
     * @param string $articleNumber
     * @return int
     */
    public static function getOnOrderQueue(string $articleNumber): int
    {
        $inQueueQuantities = Cache::get('wgr_in_queue_quantities', function() {
            $WGRService = new WGROrderQueueService();
            $inQueueQuantities = $WGRService->getQuantityInQueue();

            Cache::put('wgr_in_queue_quantities', $inQueueQuantities, 10);

            return $inQueueQuantities;
        });

        $articleQuantity = 0;

        foreach ($inQueueQuantities as $quantityArticleNumber => $quantity) {
            if ($articleNumber != $quantityArticleNumber) {
                continue;
            }

            $articleQuantity += $quantity;
        }

        return $articleQuantity;
    }

    /**
     * Returns the current net stock (stock + incoming - onOrder)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getNetStock(string $articleNumber): int
    {
        $stock = Article::where('article_number', $articleNumber)->pluck('stock_on_hand')->first();
        $incoming = self::getIncoming($articleNumber);
        $onOrder = self::getOnOrder($articleNumber);
        //$onOrderQueue = self::getOnOrderQueue($articleNumber);

        //return $stock + $incoming - $onOrder - $onOrderQueue;
        return $stock + $incoming - $onOrder;
    }

    /**
     * Returns the sales per month based on provided period
     *
     * @param string $articleNumber
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    public static function getSalesPerMonth(string $articleNumber, string $startDate, string $endDate): int
    {
        $salesPerMonthQuantities = self::getSalesPerMonthQuantities($startDate, $endDate);

        $articleSalesPerMonth = $salesPerMonthQuantities[$articleNumber] ?? 0;

        // Add order on hold if current date is between start and end date
        /*if (time() >= strtotime($startDate) && time() <= strtotime($endDate)) {

            $holdQuantity = self::getOnOrderQueue($articleNumber);

            $days = round((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24));
            $holdSalesPerMonth = $holdQuantity / ($days / 30);

            $articleSalesPerMonth += $holdSalesPerMonth;

        }*/

        return round($articleSalesPerMonth);
    }

    public static function getSalesPerMonthQuantities(string $startDate, string $endDate): array
    {
        // Try to get the results from the cache
        $salesPerMonthQuantities = Cache::get('sales_per_month_quantities_' . $startDate . $endDate);

        // If the results are not in the cache
        if ($salesPerMonthQuantities === null) {

            $days = round((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24));

            // Fetch the results from the database
            $salesPerMonthQuantities = DB::table('customer_invoice_lines')
                ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
                ->where('customer_invoices.date', '>=', $startDate)
                ->where('customer_invoices.date', '<=', $endDate)
                ->select('customer_invoice_lines.article_number', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('customer_invoice_lines.article_number')
                ->get()
                ->keyBy('article_number')
                ->map(function ($row) use ($days) {
                    return round($row->total_quantity / ($days / 30));
                })
                ->toArray();

            // Store the results in the cache for 10 minutes
            Cache::put('sales_per_month_quantities_' . $startDate . $endDate, $salesPerMonthQuantities, 10);
        }

        return $salesPerMonthQuantities;
    }

    /**
     * Return the current stock time (sales per month / netStock)
     *
     * @param string $articleNumber
     * @return int
     */
    public static function getStockTime(string $articleNumber): int
    {
        $salesPerMonth = self::getSalesPerMonth(
            $articleNumber,
            date('Y-m-d', strtotime('-6 months')),
            date('Y-m-d')
        );

        $netStock = self::getNetStock($articleNumber);

        if (!$salesPerMonth) {
            return 0;
        }

        return round($netStock / $salesPerMonth * 30, 1);
    }
}
