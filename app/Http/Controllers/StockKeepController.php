<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockKeepTransaction;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Services\WMS\StockItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockKeepController extends Controller
{
    public function get(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 50);

        $status = $request->input('status', '');
        $date = $request->input('date', null);
        $archived = (int) $request->input('archived', 0);

        $query = StockKeepTransaction::where('status', '=', $status)
            ->where('is_archived', '=', $archived);

        if ($date) {
            $query->where('created_at', 'LIKE', $date . '%');
        }

        $transactions = $query->orderBy('created_at', 'DESC')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get();

        $transactionsArray = $transactions->toArray();

        foreach ($transactionsArray as &$item) {
            $item['description'] = DB::table('articles')
                ->select('description')
                ->where('article_number', '=', $item['article_number'])
                ->value('description');
        }

        return ApiResponseController::success([
            'results' => $transactionsArray,
            'page' => $page,
            'next_page' => ($transactions->count() == $pageSize) ? $page + 1 : null,
        ]);
    }

    public function archivedDates()
    {
        $dates = DB::table('stock_keep_transactions')
            ->select(DB::raw('DATE(created_at) as date'))
            ->where('is_archived', '=', 1)
            ->groupBy('date')
            ->pluck('date');

        return ApiResponseController::success($dates->toArray());
    }

    public function archive(Request $request)
    {
        $ids = $request->input('ids');
        $ids = explode(',', $ids);

        StockKeepTransaction::whereIn('id', $ids)
            ->update(['is_archived' => 1]);

        return ApiResponseController::success();
    }

    public function stockPlace(Request $request)
    {
        $stockItemService = new StockItemService();

        $investigate = (bool) $request->input('investigate', 0);

        $stockPlaceIdentifier = $request->input('stock_place_identifier');
        $stockPlaceSplit = explode(':', $stockPlaceIdentifier);
        $stockPlace = $stockPlaceSplit[0] ?? null;
        $compartment = $stockPlaceSplit[1] ?? null;

        $stockValues = $request->input('stock_values');
        $stockValues = json_decode($stockValues, true);

        $stockPlaceObject = StockPlace::where('identifier', '=', $stockPlace)
            ->first();

        if (!$stockPlaceObject) {
            return ApiResponseController::error('Stock place not found');
        }

        $compartmentObject = StockPlaceCompartment::where('stock_place_id', '=', $stockPlaceObject->id)
            ->where('identifier', '=', $compartment)
            ->first();

        if (!$compartmentObject) {
            return ApiResponseController::error('Compartment not found');
        }

        $existingArticleNumbers = StockItem::select('article_number', DB::raw('COUNT(*) as count'))
            ->where('stock_place_compartment_id', '=', $compartmentObject->id)
            ->groupBy('article_number')
            ->pluck('count', 'article_number')
            ->toArray();

        $articleNumbers = [];

        foreach ($stockValues as $stockValue) {
            $articleNumber = $stockValue['article_number'];
            $stock = $stockValue['stock'];

            if (array_key_exists($articleNumber, $existingArticleNumbers)) {
                // Adjust current stock
                $currentStock = $existingArticleNumbers[$articleNumber];
                $diff = $stock - $currentStock;

                if ($diff == 0) {
                    continue;
                }

                if ($diff > 0) {
                    // Add stock items
                    $stockItemService->addStockItem($articleNumber, $diff, $compartmentObject, null);
                }
                else {
                    // Remove stock items
                    $stockItems = StockItem::where('article_number', $articleNumber)
                        ->where('stock_place_compartment_id', $compartmentObject->id)
                        ->limit(abs($diff))
                        ->get();

                    foreach ($stockItems as $stockItem) {
                        $stockItemService->removeStockItem($stockItem);
                    }
                }

                $this->makeTransaction(
                    $articleNumber,
                    ($stockPlace . ':' . $compartment),
                    $stock,
                    $diff,
                    $investigate
                );
            }
            else {
                // Insert new stock
                $stockItemService->addStockItem($articleNumber, $stock, $compartmentObject, null);

                $this->makeTransaction(
                    $articleNumber,
                    ($stockPlace . ':' . $compartment),
                    $stock,
                    $stock,
                    $investigate,
                );
            }

            $articleNumbers[] = $articleNumber;
        }

        // Remove items that was not provided
        foreach ($existingArticleNumbers as $articleNumber => $stock) {
            if (in_array($articleNumber, $articleNumbers)) {
                continue;
            }

            $stockItems = StockItem::where('article_number', $articleNumber)
                ->where('stock_place_compartment_id', $compartmentObject->id)
                ->limit($stock)
                ->get();

            foreach ($stockItems as $stockItem) {
                $stockItemService->removeStockItem($stockItem);
            }

            $this->makeTransaction(
                $articleNumber,
                ($stockPlace . ':' . $compartment),
                0,
                $stock * -1,
                $investigate
            );
        }

        $compartmentObject->update(['is_manual' => 0]);

        return ApiResponseController::success();
    }

    public function getStockPlaceItems(Request $request)
    {
        $stockPlaceIdentifier = $request->input('stock_place_identifier');
        $stockPlaceSplit = explode(':', $stockPlaceIdentifier);
        $stockPlace = $stockPlaceSplit[0] ?? null;
        $compartment = $stockPlaceSplit[1] ?? null;

        $stockPlaceObject = StockPlace::where('identifier', '=', $stockPlace)
            ->first();

        if (!$stockPlaceObject) {
            return ApiResponseController::success([]);
        }

        $stockCompartmentObject = StockPlaceCompartment::where('stock_place_id', '=', $stockPlaceObject->id)
            ->where('identifier', '=', $compartment)
            ->first();

        if (!$stockCompartmentObject) {
            return ApiResponseController::success([]);
        }

        $responseData = [];

        $stockItems = StockItem::where('stock_place_compartment_id', $stockCompartmentObject->id)->get();
        foreach ($stockItems as $stockItem) {
            if (!isset($responseData[$stockItem->article_number])) {
                $article = DB::table('articles')
                    ->select('articles.id', 'articles.description', 'articles.article_number', 'articles.ean', 'image.path_url')
                    ->leftJoinSub(
                        DB::table('article_images')
                            ->select('article_id', 'path_url')
                            ->whereIn('id', function($query) {
                                $query->selectRaw('MIN(id)')
                                    ->from('article_images')
                                    ->groupBy('article_id');
                            }),
                        'image',
                        'articles.id',
                        '=',
                        'image.article_id'
                    )
                    ->where('article_number', $stockItem->article_number)
                    ->first();

                $responseData[$stockItem->article_number] = [
                    'article_number' => '',
                    'stock' => 0,
                    'article' => $article,
                ];
            }

            /*
             * article_number
             * path_url
             * description
             *
             */

            $responseData[$stockItem->article_number]['stock']++;
        }

        return ApiResponseController::success($responseData);
    }

    private function makeTransaction(string $articleNumber, string $identifier, int $value, int $diff, bool $investigate)
    {
        StockKeepTransaction::create([
            'article_number' => $articleNumber,
            'identifiers' => $identifier,
            'values' => $value,
            'diffs' => $diff,
            'type' => 'manual',
            'status' => $investigate ? 'investigation' : 'completed'
        ]);
    }
}
