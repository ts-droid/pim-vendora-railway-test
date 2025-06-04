<?php

namespace App\Services\Todo;

use App\Enums\TodoType;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Utilities\WarehouseHelper;
use Illuminate\Support\Facades\DB;

class TodoItemMetaService
{
    public function getMeta(TodoType $type, array $data): array
    {
        switch ($type) {
            case TodoType::CollectArticle:
                return $this->getCollectArticleMeta($data);
        }

        return [];
    }

    private function getCollectArticleMeta(array $data): array
    {
        $article = DB::table('articles')
            ->select('*')
            ->where('id', ($data['article_id'] ?? 0))
            ->first();

        $image = ArticleImage::select('path_url')
            ->where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->limit(1)
            ->first();

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

        return [
            'id' => $article->id,
            'article_number' => $article->article_number,
            'ean' => $article->ean,
            'description' => $article->description,
            'width' => $article->width,
            'height' => $article->height,
            'depth' => $article->depth,
            'weight' => $article->weight,
            'inner_box' => $article->inner_box,
            'master_box' => $article->master_box,
            'image' => $image ? $image->path_url : null,
            'package_image_front' => $article->package_image_front,
            'package_image_front_url' => $article->package_image_front_url,
            'package_image_back' => $article->package_image_back,
            'package_image_back_url' => $article->package_image_back_url,
            'total_stock' => $article->stock_manageable,
            'reserved_stock' => WarehouseHelper::getPickedStock($article->article_number),
            'incoming_stock' => $purchaseData->incoming_quantity ?? 0,
            'oldest_purchase_date' => $purchaseData->oldest_purchase_date ?? '',
            'serial_number_management' => $article->serial_number_management ? 'Active' : 'Inactive',
            'locations' => WarehouseHelper::getArticleLocationsWithStock($article->article_number),
        ];
    }
}
