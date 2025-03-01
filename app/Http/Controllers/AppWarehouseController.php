<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockKeepTodo;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceCompartmentReservation;
use App\Services\Todo\TodoItemService;
use App\Services\Todo\TodoService;
use App\Services\WMS\StockItemService;
use App\Utilities\WarehouseHelper;
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
            ->orderByRaw("FIELD(type, ?, ?)", ['refill', 'organization'])
            ->orderBy('id', 'ASC')
            ->get();

        $articleNumbers = $stockItemMovements->pluck('article_number')->toArray();
        $articleData = $this->getArticleData($articleNumbers);

        foreach ($stockItemMovements as $stockItemMovement) {
            $stockItemMovement->article = $articleData[$stockItemMovement->article_number] ?? null;
        }

        return ApiResponseController::success($stockItemMovements->toArray());
    }

    public function createMovement(Request $request)
    {
        try {
            $signature = get_display_name();

            $articleNumber = $request->input('article_number');
            $quantity = (int) $request->input('quantity');

            $fromStockPlace = str_replace('--', '', $request->input('fromStockPlace', ''));
            $fromCompartment = str_replace('--', '', $request->input('fromCompartment', ''));

            $toStockPlace = str_replace('--', '', $request->input('toStockPlace', ''));
            $toCompartment = str_replace('--', '', $request->input('toCompartment', ''));

            $fromStockPlaceObject = null;
            $fromCompartmentObject = null;

            $toStockPlaceObject = null;
            $toCompartmentObject = null;

            if ($quantity <= 0) {
                return ApiResponseController::error('Quantity must be greater than 0.');
            }

            $article = Article::where('article_number', $articleNumber)->first();
            if (!$article) {
                return ApiResponseController::error('Article not found.');
            }

            // Validate from location
            if ($fromStockPlace) {
                $fromStockPlaceObject = StockPlace::where('identifier', $fromStockPlace)->first();
                if (!$fromStockPlaceObject) {
                    return ApiResponseController::error('Stock place not found.');
                }

                $fromCompartmentObject = StockPlaceCompartment::where('stock_place_id', $fromStockPlaceObject->id)->where('identifier', $fromCompartment)->first();
                if (!$fromCompartmentObject) {
                    return ApiResponseController::error('Compartment not found.');
                }
            }


            // Validate to location
            $toStockPlaceObject = StockPlace::where('identifier', $toStockPlace)->first();
            if (!$toStockPlaceObject) {
                return ApiResponseController::error('Stock place not found.');
            }

            $toCompartmentObject = StockPlaceCompartment::where('stock_place_id', $toStockPlaceObject->id)->where('identifier', $toCompartment)->first();
            if (!$toCompartmentObject) {
                return ApiResponseController::error('Compartment not found.');
            }

            // Validate article & quantity
            if ($fromStockPlace) {
                $stockPlaceQuantity = (int) StockItem::where('article_number', $articleNumber)
                    ->where('stock_place_compartment_id', ($fromCompartmentObject->id ?? 0))
                    ->count();

                if ($stockPlaceQuantity < $quantity) {
                    return ApiResponseController::error('Not enough stock in the selected compartment to item move from.');
                }
            }
            else {
                $managedStock = (int) StockItem::where('article_number', $articleNumber)->count();
                $unmanagedStock = $article->stock - $managedStock;

                if ($unmanagedStock < $quantity) {
                    return ApiResponseController::error('Not enough unmanaged stock in the warehouse to move this quantity.');
                }
            }


            // Make the movement
            $stockItemService = new StockItemService();

            if ($fromCompartmentObject) {
                $response = $stockItemService->moveStockItems(
                    $article->article_number,
                    $quantity,
                    $fromCompartmentObject,
                    $toCompartmentObject,
                    $signature
                );
            }
            else {
                $response = $stockItemService->addStockItem(
                    $article->article_number,
                    $quantity,
                    $toCompartmentObject,
                    $signature
                );
            }

            if (!$response['success']) {
                return ApiResponseController::error($response['message']);
            }

            return ApiResponseController::success();
        } catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }

    public function getMovement(StockItemMovement $stockItemMovement)
    {
        $stockItemMovement = StockItemMovement::with(
                'fromStockPlaceCompartment',
                'fromStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment',
                'toStockPlaceCompartment.stockPlace',
                'toStockPlaceCompartment.sections'
            )
            ->where('id', $stockItemMovement->id)
            ->first();

        $articleData = $this->getArticleData([$stockItemMovement->article_number], true);

        $stockItemMovement->article = $articleData[$stockItemMovement->article_number] ?? null;

        return ApiResponseController::success($stockItemMovement->toArray());
    }

    public function confirmMovement(Request $request, StockItemMovement $stockItemMovement)
    {
        $signature = get_display_name();

        $stockItemService = new StockItemService();

        $quantity = (int) $request->input('quantity');

        if ($quantity < 0) {
            return ApiResponseController::error('Quantity must be greater than or equal to 0.');
        }

        if ($quantity > 0) {
            $stockItemMovement->update(['quantity' => $quantity]);
        }

        $stockItemMovement->load('toStockPlaceCompartment', 'fromStockPlaceCompartment');

        $response = null;

        if ($stockItemMovement->from_stock_place_compartment && $stockItemMovement->to_stock_place_compartment) {
            // Move the item from the existing compartment to the new compartment
            $response = $stockItemService->moveStockItems(
                $stockItemMovement->article_number,
                $stockItemMovement->quantity,
                $stockItemMovement->fromStockPlaceCompartment,
                $stockItemMovement->toStockPlaceCompartment,
                $signature
            );
        }
        else {
            if ($stockItemMovement->to_stock_place_compartment) {
                // Insert the item to the new compartment
                $response = $stockItemService->addStockItem(
                    $stockItemMovement->article_number,
                    $stockItemMovement->quantity,
                    $stockItemMovement->toStockPlaceCompartment,
                    $signature
                );
            }
            else if ($stockItemMovement->from_stock_place_compartment) {
                $stockItems = StockItem::where('stock_place_compartment_id', $stockItemMovement->from_stock_place_compartment)
                    ->limit($stockItemMovement->quantity)
                    ->get();

                if ($stockItems->count() < $stockItemMovement->quantity) {
                    return ApiResponseController::error('Not enough stock in the selected compartment to item move from.');
                }

                foreach ($stockItems as $stockItem) {
                    $response = $stockItemService->removeStockItem($stockItem, $signature);
                }
            }
        }

        if (!$response) {
            return ApiResponseController::error('Invalid movement.');
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

        // Remove old reservations
        StockPlaceCompartmentReservation::where('reserved_until', '<', date('Y-m-d H:i:s'))->delete();

        // Reserv the stock compartment for 30 minutes
        StockPlaceCompartmentReservation::create([
            'stock_place_compartment_id' => $stockItemMovement->to_stock_place_compartment,
            'reserved_until' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
        ]);

        return ApiResponseController::success();
    }

    public function stockKeepTodo(StockItemMovement $stockItemMovement)
    {
        StockKeepTodo::create([
            'reference' => $stockItemMovement->article_number,
            'type' => 'article'
        ]);

        $stockItemMovement->delete();

        return ApiResponseController::success();
    }

    public function measurementTodo(StockItemMovement $stockItemMovement)
    {
        $articleID = (int) DB::table('articles')
            ->select('id')
            ->where('article_number', $stockItemMovement->article_number)
            ->value('id');

        if (!$articleID) {
            return ApiResponseController::error('Article not found.');
        }

        $service = new TodoItemService();
        $service->createCollectArticle(
            $articleID,
            'size',
            0,
            'system'
        );

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

    private function getArticleData(array $articleNumbers, bool $detailed = false)
    {
        $articles = DB::table('articles')
            ->select(
                'id', 'article_number', 'ean', 'description', 'stock_manageable as stock',
                'inner_box', 'master_box', 'package_image_front_url', 'width', 'height', 'depth'
            )
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

                $article->reserved_stock = WarehouseHelper::getReservedStock($article->article_number);
                $article->incoming_stock = $purchaseData->incoming_quantity ?? 0;
                $article->oldest_purchase_date = $purchaseData->oldest_purchase_date ?? '';

                $article->locations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);
            }

            $articlesData[$article->article_number] = $article;
        }

        return $articlesData;
    }
}
