<?php

namespace App\Http\Controllers;

use App\Models\CompartmentSection;
use App\Models\StockItem;
use App\Models\StockKeepTodo;
use App\Models\StockKeepTransaction;
use App\Models\StockPlace;
use App\Models\StockPlaceCompartment;
use App\Services\WMS\StockItemService;
use App\Services\WMS\StockPlaceService;
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
            ->update([
                'status' => 'completed',
                'is_archived' => 1
            ]);

        return ApiResponseController::success();
    }

    public function stockPlace(Request $request)
    {
        $signature = get_display_name();

        $stockItemService = new StockItemService();

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

        $isManual = $compartmentObject->is_manual;

        $existingArticleNumbers = StockItem::select('article_number', DB::raw('COUNT(*) as count'))
            ->where('stock_place_compartment_id', '=', $compartmentObject->id)
            ->groupBy('article_number')
            ->pluck('count', 'article_number')
            ->toArray();

        $articleNumbers = [];

        foreach ($stockValues as $stockValue) {
            $articleNumber = $stockValue['article_number'];
            $stock = $stockValue['stock'];

            $articleNumbers[] = $articleNumber;

            if (array_key_exists($articleNumber, $existingArticleNumbers)) {
                // Adjust current stock
                $currentStock = $existingArticleNumbers[$articleNumber];
                $diff = $stock - $currentStock;

                if ($diff == 0) {
                    $this->makeTransaction(
                        $articleNumber,
                        ($stockPlace . ':' . $compartment),
                        $stock,
                        $diff,
                        false
                    );

                    continue;
                }

                if ($diff > 0) {
                    // Add stock items
                    $stockItemService->addStockItem($articleNumber, $diff, $compartmentObject, $signature);
                }
                else {
                    // Remove stock items
                    $stockItems = StockItem::where('article_number', $articleNumber)
                        ->where('stock_place_compartment_id', $compartmentObject->id)
                        ->limit(abs($diff))
                        ->get();

                    foreach ($stockItems as $stockItem) {
                        $stockItemService->removeStockItem($stockItem, $signature);
                    }
                }

                $this->makeTransaction(
                    $articleNumber,
                    ($stockPlace . ':' . $compartment),
                    $stock,
                    $diff,
                    ($isManual ? false : true)
                );
            }
            else {
                // Insert new stock
                $stockItemService->addStockItem($articleNumber, $stock, $compartmentObject, $signature);

                $this->makeTransaction(
                    $articleNumber,
                    ($stockPlace . ':' . $compartment),
                    $stock,
                    $stock,
                    ($isManual ? false : true)
                );
            }
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
                $stockItemService->removeStockItem($stockItem, $signature);
            }

            $this->makeTransaction(
                $articleNumber,
                ($stockPlace . ':' . $compartment),
                0,
                $stock * -1,
                true
            );
        }


        // Update number of sections (if manual)
        if ($isManual) {
            $sections = array_unique($articleNumbers);
            $sections = array_filter($sections);

            if ($compartmentObject->sections->count() != $sections) {
                if ($compartmentObject->sections->count() < $sections) {
                    // Add more sections
                    for ($i = 0;$i < ($sections - $compartmentObject->sections->count());$i++) {
                        CompartmentSection::create(['stock_place_compartment_id' => $compartmentObject->id]);
                    }

                }
                else {
                    // Remove empty sections
                    $sectionIDs = $compartmentObject->sections->pluck('id')->values()->toArray();

                    $deleteIDs = array_slice($sectionIDs, ($compartmentObject->sections->count() - $sections));

                    CompartmentSection::whereIn('id', $deleteIDs)->delete();
                }
            }
        }

        $compartmentObject->update(['is_manual' => 0]);

        // Remove tasks to stock keep this compartment
        StockKeepTodo::where('type', 'compartment')
            ->where('reference', $stockPlaceIdentifier)
            ->delete();

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

            $responseData[$stockItem->article_number]['stock']++;
        }

        return ApiResponseController::success(array_values($responseData));
    }

    public function getTodo()
    {
        $todos = StockKeepTodo::orderBy('created_at', 'ASC')
            ->limit(50)
            ->get();

        if ($todos) {
            foreach ($todos as &$todo) {

                switch ($todo->type) {
                    case 'article':
                        $todo->meta_data = DB::table('articles')
                            ->select('id', 'ean', 'article_number', 'description')
                            ->where('article_number', $todo->reference)
                            ->first();
                        break;


                    case 'compartment':
                        $stockPlaceService = new StockPlaceService();

                        $todo->meta_data = [
                            'stock_place_compartment' => $stockPlaceService->getCompartmentByIdentifier($todo->reference)
                        ];
                        break;
                }

            }
        }

        return ApiResponseController::success($todos->toArray());
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
