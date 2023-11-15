<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;

class PurchaseOrderGenerator
{
    /**
     * Generates a purchase order for all suppliers.
     *
     * @return void
     */
    public function createAllSupplierOrders(): void
    {
        $suppliers = Supplier::all();

        foreach ($suppliers as $supplier) {
            $this->createSupplierOrder($supplier);
        }
    }

    /**
     * Generates a purchase order for a specific supplier.
     *
     * @param Supplier $supplier
     * @return array
     */
    public function createSupplierOrder(Supplier $supplier): array
    {
        // Fetch all articles for the supplier
        $articles = Article::where('supplier_number', $supplier->number)->get();

        if (!$articles->count()) {
            return ['success' => false];
        }

        // Collect all articles that need to be ordered
        $orderLines = collect();

        $lineKey = 0;

        foreach ($articles as $article) {
            if (!$this->needToOrderArticle($article)) {
                continue;
            }

            $quantity = $this->getQuantityToOrder($article);

            if (!$quantity) {
                continue;
            }

            $orderLines->push([
                'line_key' => $lineKey++,
                'article_number' => $article->article_number,
                'description' => $article->description,
                'quantity' => $quantity,
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
        ]);

        foreach ($orderLines as $orderLine) {
            $orderLine['purchase_order_id'] = $purchaseOrder->id;

            PurchaseOrderLine::create($orderLine);
        }

        // TODO: Create a task

        return [
            'success' => true,
            'purchase_order_id' => $purchaseOrder->id,
            'task' => null,
        ];
    }

    /**
     * Returns whether an article needs to be ordered.
     *
     * @param Article $article
     * @return bool
     */
    private function needToOrderArticle(Article $article): bool
    {
        if (!$article->stock) {
            return true;
        }

        // TODO: Add more logic to decide if an article needs to be ordered

        return false;
    }

    /**
     * Returns the quantity to order for a specific article.
     *
     * @param Article $article
     * @return int
     */
    private function getQuantityToOrder(Article $article): int
    {
        $salesVolume = (int) $article->sales_30_days;

        $quantityOnSalesOrder = 0; // TODO: Get the quantity on sales order

        $quantityOnPurchaseOrder = 0; // TODO: Get the quantity on purchase order

        $quantityToOrder = $salesVolume + $quantityOnSalesOrder - $quantityOnPurchaseOrder;

        return max(0, $quantityToOrder);
    }
}
