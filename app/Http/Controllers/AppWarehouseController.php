<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\StockItem;
use App\Models\StockItemMovement;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Models\StockPlaceCompartmentReservation;
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

    public function createMovement(Request $request)
    {
        $articleNumber = $request->input('article_number');
        $quantity = (int) $request->input('quantity');

        $fromStockPlace = str_replace('--', '', $request->input('fromStockPlace', ''));
        $fromCompartment = str_replace('--', '', $request->input('fromCompartment', ''));
        $fromSection = (int) str_replace('--', '', $request->input('fromSection', ''));

        $toStockPlace = str_replace('--', '', $request->input('toStockPlace', ''));
        $toCompartment = str_replace('--', '', $request->input('toCompartment', ''));
        $toSection = (int) str_replace('--', '', $request->input('toSection', ''));

        $fromStockPlaceObject = null;
        $fromCompartmentObject = null;
        $fromSectionObject = null;

        $toStockPlaceObject = null;
        $toCompartmentObject = null;
        $toSectionObject = null;

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

            if ($fromCompartmentObject->sections->count() > 0) {
                for ($i = 1;$i <= $fromCompartmentObject->sections->count();$i++) {
                    if ($fromSection != $i) {
                        continue;
                    }

                    $fromSectionObject = $fromCompartmentObject->sections[$i - 1];
                }

                if (!$fromSectionObject) {
                    return ApiResponseController::error('Section not found.');
                }
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

        if ($toCompartmentObject->sections->count() > 0) {
            for ($i = 1;$i <= $toCompartmentObject->sections->count();$i++) {
                if ($toSection != $i) {
                    continue;
                }

                $toSectionObject = $toCompartmentObject->sections[$i - 1];
            }

            if (!$toSectionObject) {
                return ApiResponseController::error('Section not found.');
            }
        }


        // Validate article & quantity
        if ($fromStockPlace) {
            $stockPlaceQuantity = (int) StockItem::where('article_number', $articleNumber)
                ->where('stock_place_compartment_id', ($fromCompartmentObject->id ?? 0))
                ->where('compartment_section_id', ($fromSectionObject->id ?? 0))
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
                $fromSectionObject,
                $toCompartmentObject,
                $toSectionObject
            );
        }
        else {
            $response = $stockItemService->addStockItem(
                $articleNumber->article_number,
                $quantity,
                $toCompartmentObject,
                $toSectionObject
            );
        }

        if (!$response['success']) {
            return ApiResponseController::error($response['message']);
        }

        return ApiResponseController::success();
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
        $stockItemService = new StockItemService();

        $quantity = (int) $request->input('quantity');
        if ($quantity > 0) {
            /*if ($quantity > $stockItemMovement->quantity) {
                return ApiResponseController::error('You can not move more than the suggested quantity.');
            }*/

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
                $stockItemMovement->fromCompartmentSection ?: null,
                $stockItemMovement->toStockPlaceCompartment,
                $stockItemMovement->toCompartmentSection ?: null,
            );
        }
        else {
            if ($stockItemMovement->to_stock_place_compartment) {
                // Insert the item to the new compartment
                $response = $stockItemService->addStockItem(
                    $stockItemMovement->article_number,
                    $stockItemMovement->quantity,
                    $stockItemMovement->toStockPlaceCompartment,
                    $stockItemMovement->toCompartmentSection ?: null,
                );
            }
            else if ($stockItemMovement->from_stock_place_compartment) {
                $stockItems = StockItem::where('stock_place_compartment_id', $stockItemMovement->from_stock_place_compartment)
                    ->where('compartment_section_id', $stockItemMovement->from_compartment_section)
                    ->limit($stockItemMovement->quantity)
                    ->get();

                foreach ($stockItems as $stockItem) {
                    $response = $stockItemService->removeStockItem($stockItem);
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
