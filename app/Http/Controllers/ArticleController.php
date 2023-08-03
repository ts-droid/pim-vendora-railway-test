<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Article::class, $request);

        $query = $this->getQueryWithFilter(Article::class, $filter);

        $articles = $query->with('stock_logs')->get();

        return ApiResponseController::success($articles->toArray());
    }

    public function getImages(Request $request, Article $article)
    {
        $images = ArticleImage::where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        return ApiResponseController::success($images->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'article_number' => 'required|string',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $article = Article::create([
            'external_id' => $request->external_id,
            'article_number' => $request->article_number,
            'description' => $request->description,
            'ean' => (string) ($request->ean ?? ''),
            'wright_article_number' => (string) ($request->wright_article_number ?? ''),
            'supplier_number' => (string) ($request->supplier_number ?? ''),
            'cost_price_avg' => (float) ($request->cost_price_avg ?? 0),
            'stock' => (int) ($request->stock ?? 0),
            'hs_code' => (string) ($request->hs_code ?? ''),
            'origin_country' => (string) ($request->origin_country ?? ''),
            'inner_box' => (int) ($request->inner_box ?? 0),
            'master_box' => (int) ($request->master_box ?? 0),
            'width' => (float) ($request->width ?? 0),
            'height' => (float) ($request->height ?? 0),
            'depth' => (float) ($request->depth ?? 0),
            'master_box_width' => (float) ($request->master_box_width ?? 0),
            'master_box_height' => (float) ($request->master_box_height ?? 0),
            'master_box_depth' => (float) ($request->master_box_depth ?? 0),
            'inner_box_width' => (float) ($request->inner_box_width ?? 0),
            'inner_box_height' => (float) ($request->inner_box_height ?? 0),
            'inner_box_depth' => (float) ($request->inner_box_depth ?? 0),
            'weight' => (float) ($request->weight ?? 0),
            'master_box_weight' => (float) ($request->master_box_weight ?? 0),
            'inner_box_weight' => (float) ($request->inner_box_weight ?? 0),
            'brand' => (string) ($request->brand ?? ''),
            'is_webshop' => (int) ($request->is_webshop ?? 0),
        ]);

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        return ApiResponseController::success([$article->toArray()]);
    }

    public function update(Request $request, Article $article)
    {
        $fillables = (new Article)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $article->{$key} = $value;
            }
        }

        $article->save();

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        // Upload images
        if (isset($request->images) && is_array($request->images)) {
            $listOrder = 0;

            foreach ($request->images as $imageURL) {
                $this->uploadArticleImage($article, $imageURL, $listOrder++);
            }
        }

        return ApiResponseController::success([$article->toArray()]);
    }

    public function uploadArticleImage(Article $article, string $url, int $listOrder = 0): void
    {
        // Extract the filename from the URL
        $path = parse_url(trim($url), PHP_URL_PATH);
        $filename = $article->id . basename($path);

		// Fetch the image from the URL
		$imageContent = @file_get_contents($url);

		if (!$imageContent) {
			return;
		}

        // Remove existing image with the same filename
        $existingImage = ArticleImage::where('filename', $filename)
            ->where('article_id', $article->id)
            ->first();

        if ($existingImage) {
            $this->deleteArticleImage($existingImage);
        }

        // Save the image to the storage
        Storage::disk('public')->put($filename, $imageContent);

        // Save the image int the database
        ArticleImage::create([
            'article_id' => $article->id,
            'filename' => $filename,
            'path_url' => 'storage/' . $filename,
            'size' => Storage::disk('public')->size($filename),
            'list_order' => $listOrder
        ]);
    }

    public function deleteArticleImage(ArticleImage $articleImage): void
    {
        Storage::disk('public')->delete($articleImage->filename);

        $articleImage->delete();
    }
}
