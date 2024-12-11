<?php

namespace App\Http\Controllers;

use App\Models\StockItemMovement;
use App\Services\WMS\StockItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppWarehouseController extends Controller
{
    public function getMovements()
    {
        $stockItemMovements = StockItemMovement::with(
                'fromStockPlaceCompartment',
                'fromStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment',
                'toStockPlaceCompartment.stockPlace'
            )
            ->orderBy('id', 'ASC')
            ->get();

        $articleNumbers = $stockItemMovements->pluck('article_number')->toArray();
        $articleData = $this->getArticleData($articleNumbers);

        foreach ($stockItemMovements as $stockItemMovement) {
            $stockItemMovement->article = $articleData[$stockItemMovement->article_number] ?? null;
        }

        return ApiResponseController::success($stockItemMovements->toArray());
    }

    public function getMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement = StockItemMovement::with(
                'fromStockPlaceCompartment',
                'fromStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment',
                'toStockPlaceCompartment.stockPlace'
            )
            ->where('id', $stockItemMovement->id)
            ->first();

        $articleData = $this->getArticleData([$stockItemMovement->article_number], true);

        $stockItemMovement->article = $articleData[$stockItemMovement->article_number] ?? null;

        return ApiResponseController::success($stockItemMovement->toArray());
    }

    public function confirmMovement(Request $request, StockItemMovement $stockItemMovement)
    {
        $stockItemService = new StockItemService();

        $quantity = (int) $request->input('quantity');
        if ($quantity > 0) {
            if ($quantity > $stockItemMovement->quantity) {
                return ApiResponseController::error('You can not move more than the suggested quantity.');
            }

            $stockItemMovement->update(['quantity' => $quantity]);
        }

        $stockItemMovement->load('toStockPlaceCompartment', 'fromStockPlaceCompartment');

        if ($stockItemMovement->from_stock_place_compartment) {
            // Move the item from the existing compartment to the new compartment
            $response = $stockItemService->moveStockItems(
                $stockItemMovement->article_number,
                $stockItemMovement->fromStockPlaceCompartment,
                $stockItemMovement->toStockPlaceCompartment,
                $stockItemMovement->quantity
            );
        }
        else {
            // Insert the item to the new compartment
            $response = $stockItemService->addStockItem(
                $stockItemMovement->article_number,
                $stockItemMovement->toStockPlaceCompartment,
                $stockItemMovement->quantity
            );
        }

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        $stockItemMovement->delete();

        return ApiResponseController::success();
    }

    public function investigateMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['is_investigation' => 1]);

        return ApiResponseController::success();
    }

    public function pingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => time()]);

        return ApiResponseController::success();
    }

    public function unpingMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement->update(['ping_at' => 0]);

        return ApiResponseController::success();
    }

    private function getArticleData(array $articleNumbers, bool $detailed = false)
    {
        $articles = DB::table('articles')
            ->select('id', 'article_number', 'description', 'stock_on_hand AS stock', 'inner_box', 'master_box')
            ->whereIn('article_number', $articleNumbers)
            ->get();

        $articleIDs = $articles->pluck('id');

        $articleImages = DB::table('article_images')
            ->select('article_id', 'path_url')
            ->whereIn('article_id', $articleIDs)
            ->where('list_order', 0)
            ->get();

        $articlesData = [];

        foreach ($articles as $article) {
            // Fetch main image
            $article->image = null;
            foreach($articleImages as $articleImage) {
                if ($articleImage->article_id == $article->id) {
                    $article->image = $articleImage->path_url;
                    break;
                }
            }

            // Fetch detailed data
            if ($detailed) {
                $purchaseData = DB::table('purchase_order_lines')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                    ->selectRaw('
                        SUM(purchase_order_lines.quantity - purchase_order_lines.quantity_received) AS incoming_quantity,
                        MIN(purchase_orders.date) AS oldest_purchase_date
                    ')
                    ->where('purchase_order_lines.article_number', '=', $article->article_number)
                    ->where('purchase_order_lines.is_completed', 0)
                    ->where('purchase_orders.date', '>=', '2023-01-01')
                    ->first();

                $article->incoming_stock = $purchaseData->incoming_quantity ?? 0;
                $article->oldest_purchase_date = $purchaseData->oldest_purchase_date ?? '';
            }

            $articlesData[$article->article_number] = $article;
        }

        return $articlesData;
    }
}
