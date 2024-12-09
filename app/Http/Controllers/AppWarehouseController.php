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

        $articleData = $this->getArticleData([$stockItemMovement->article_number]);

        $stockItemMovement->article = $articleData[$stockItemMovement->article_number] ?? null;

        return ApiResponseController::success($stockItemMovement->toArray());
    }

    public function confirmMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemService = new StockItemService();

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

    private function getArticleData(array $articleNumbers)
    {
        $articles = DB::table('articles')
            ->select('id', 'article_number', 'description')
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
            $article->image = null;

            foreach($articleImages as $articleImage) {
                if ($articleImage->article_id == $article->id) {
                    $article->image = $articleImage->path_url;
                    break;
                }
            }

            $articlesData[$article->article_number] = $article;
        }

        return $articlesData;
    }
}
