<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Utilities\ImageBackgroundAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function getSimple(Request $request)
    {
        $query = DB::table('articles')
            ->select('id', 'article_number', 'description', 'inner_box', 'master_box');

        if ($request->has('status')) {
            $query->where('status', $request->input('status', ''));
        }

        if ($request->has('hide_po_system')) {
            $query->where('hide_po_system', $request->input('hide_po_system'));
        }

        $articles = $query->get();

        $supplierPrices = DB::table('supplier_article_prices')
            ->select('article_number', 'price', 'currency')
            ->get()
            ->keyBy('article_number');

        if ($articles) {
            foreach ($articles as &$article) {
                $article->supplier_price = 0;
                $article->supplier_price_currency = '';

                if (!isset($supplierPrices[$article->article_number])) {
                    continue;
                }

                $article->supplier_price = $supplierPrices[$article->article_number]->price;
                $article->supplier_price_currency = $supplierPrices[$article->article_number]->currency;
            }
        }

        return ApiResponseController::success($articles->toArray());
    }

    public function getBasic(Request $request)
    {
        // Get input parameters
        $filters = $request->input('filter');
        $columns = $request->input('columns', ['*']);
        $currency = $request->input('currency');

        // Build query
        $query = DB::table('articles')
            ->select($columns);

        if ($filters) {
            foreach ($filters as $filter) {
                $count = count($filter);

                if ($count === 3) {
                    $query->where($filter[0], $filter[1], $filter[2]);
                }
                elseif ($count === 2) {
                    $query->whereIn($filter[0], $filter[1]);
                }
            }
        }

        // Execute query
        $articles = $query->get()->toArray();

        // Convert article objects into an array
        $articles = array_map(function ($article) {
            return get_object_vars($article);
        }, $articles);

        // Convert results to requested currency
        if ($currency && $articles) {
            $currencyConverter = new CurrencyConvertController();

            foreach ($articles as &$article) {
                $currencyConverter->convertArray(
                    $article,
                    ['cost_price_avg', 'external_cost'],
                    'SEK',
                    $currency,
                    date('Y-m-d')
                );
            }
        }

        return ApiResponseController::success($articles);
    }

    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Article::class, $request);

        $query = $this->getQueryWithFilter(Article::class, $filter);

        $articles = $query->with('stock_logs')->get();

        if ($request->has('only_fields')) {
            $fields = explode(',', $request->only_fields);

            $newArticles = [];
            foreach ($articles as $article) {
                $article = $article->toArray();
                $newArticle = [];

                foreach ($fields as $field) {
                    if (isset($article[$field])) {
                        $newArticle[$field] = $article[$field];
                    }
                }

                $newArticles[] = $newArticle;
            }
            $articles = $newArticles;
        }
        else {
            $articles = $articles->toArray();
        }

        if (empty($fields) || in_array('category_ids', $fields)) {
            $articleCategoryController = new ArticleCategoryController();

            foreach ($articles as &$article) {

                $article['categories'] = [];

                if ($article['category_ids'] && is_array($article['category_ids'])) {
                    $article['categories'] = $articleCategoryController->getCategoryTree($article['category_ids']);
                }
            }
        }

        // Convert results to requested currency
        $convertedCurrency = $request->get('convert_to_currency', '');
        if ($convertedCurrency) {

            $currencyConverter = new CurrencyConvertController();

            foreach ($articles as &$article) {
                $currencyConverter->convertArray(
                    $article,
                    ['cost_price_avg', 'external_cost'],
                    'SEK',
                    $convertedCurrency,
                    date('Y-m-d')
                );
            }

        }

        return ApiResponseController::success($articles);
    }

    public function getImages(Request $request, Article $article)
    {
        $images = ArticleImage::where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        return ApiResponseController::success($images->toArray());
    }

    public function updateImage(Request $request, Article $article, ArticleImage $articleImage)
    {
        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            if ($request->has('alt_text_' . $locale->language_code)) {
                $articleImage->{'alt_text_' . $locale->language_code} = $request->{'alt_text_' . $locale->language_code};
            }
        }

        $articleImage->save();

        return ApiResponseController::success($articleImage->toArray());
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

        $data = [
            'external_id' => $request->external_id,
            'article_number' => $request->article_number,
            'status' => (string) ($request->status ?? 'Active'),
            'description' => $request->description,
            'ean' => (string) ($request->ean ?? ''),
            'wright_article_number' => (string) ($request->wright_article_number ?? ''),
            'supplier_number' => (string) ($request->supplier_number ?? ''),
            'cost_price_avg' => (float) ($request->cost_price_avg ?? 0),
            'external_cost' => (float) ($request->external_cost ?? 0),
            'rek_price_SEK' => (float) ($request->rek_price_SEK ?? 0),
            'rek_price_EUR' => (float) ($request->rek_price_EUR ?? 0),
            'rek_price_DKK' => (float) ($request->rek_price_DKK ?? 0),
            'rek_price_NOK' => (float) ($request->rek_price_NOK ?? 0),
            'retail_price_SEK' => (float) ($request->retail_price_SEK ?? 0),
            'retail_price_EUR' => (float) ($request->retail_price_EUR ?? 0),
            'retail_price_DKK' => (float) ($request->retail_price_DKK ?? 0),
            'retail_price_NOK' => (float) ($request->retail_price_NOK ?? 0),
            'stock' => (int) ($request->stock ?? 0),
            'stock_warehouse' => (int) ($request->stock_warehouse ?? 0),
            'stock_on_hand' => (int) ($request->stock_on_hand ?? 0),
            'stock_available_for_shipment' => (int) ($request->stock_available_for_shipment ?? 0),
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
            'sales_30_days' => (int) ($request->sales_30_days ?? 0),
            'webshop_created_at' => (string) ($request->webshop_created_at ?? ''),
            'review_links' => (string) ($request->review_links ?? '[]'),
            'is_hidden' => (int) ($request->is_hidden ?? 0),
            'category_ids' => [],
        ];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            $data['shop_title_' . $locale->language_code] = (string) ($request->{'shop_title_' . $locale->language_code} ?? '');
            $data['shop_description_' . $locale->language_code] = (string) ($request->{'shop_description_' . $locale->language_code} ?? '');
            $data['meta_title_' . $locale->language_code] = (string) ($request->{'meta_title_' . $locale->language_code} ?? '');
            $data['meta_description_' . $locale->language_code] = (string) ($request->{'meta_description_' . $locale->language_code} ?? '');
        }

        $article = Article::create($data);

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        return ApiResponseController::success([$article->toArray()]);
    }

    public function updateMany(Request $request)
    {
        $articles = $request->get('articles');

        if ($articles) {
            foreach ($articles as $article) {
                $articleNumber = (string) $article['article_number'] ?? '';

                if (!$articleNumber) {
                    continue;
                }

                $fillables = get_model_attributes(Article::class);
                $allowedUpdates = array_intersect_key($article, array_flip($fillables));

                Article::where('article_number', $articleNumber)->update($allowedUpdates);
            }
        }

        return ApiResponseController::success();
    }

    public function update(Request $request, Article $article)
    {
        $fillables = get_model_attributes(Article::class);

        $updates = $request->all();

        $allowedUpdated = array_intersect_key($updates, array_flip($fillables));

        $article->update($allowedUpdated);

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        // Upload images
        if (isset($request->images) && is_array($request->images)) {
            $listOrder = 0;

            $imageHashes = [];

            foreach ($request->images as $imageURL) {
                $imageHashes[] = $this->uploadArticleImage($article, $imageURL, $listOrder++);
            }

            // Remove images that are not in the list
            $removedImages = ArticleImage::where('article_id', $article->id)
                ->where(function($query) use ($imageHashes) {
                    $query->whereNotIn('hash', $imageHashes)
                        ->orWhereNull('hash');
                })
                ->get();

            if ($removedImages) {
                foreach ($removedImages as $removedImage) {
                    $this->deleteArticleImage($removedImage);
                }
            }
        }

        return ApiResponseController::success([$article->toArray()]);
    }

    public function uploadArticleImage(Article $article, string $url, int $listOrder = 0): string
    {
        // Extract the filename from the URL
        $path = parse_url(trim($url), PHP_URL_PATH);
        $filename = $article->id . basename($path);

		// Fetch the image from the URL
		$imageContent = @file_get_contents($url);

		if (!$imageContent) {
			return '';
		}

        $contentHash = md5($imageContent);

        $existingImage = ArticleImage::where('hash', $contentHash)
            ->where('article_id', $article->id)
            ->first();

        if ($existingImage) {
            // Update existing image
            $existingImage->update([
                'list_order' => $listOrder,
            ]);
        }
        else {
            // Upload new image
            $filename = DoSpacesController::store($filename, $imageContent, true);

            $imageSize = DoSpacesController::getSize($filename);

            // Check if the image has a solid background color
            $solidBackground = ImageBackgroundAnalyzer::hasSolidBackground($imageContent, 'topbar');

            ArticleImage::create([
                'article_id' => $article->id,
                'filename' => $filename,
                'path_url' => DoSpacesController::getURL($filename),
                'size' => $imageSize,
                'solid_background' => $solidBackground ? 1 : 0,
                'list_order' => $listOrder,
                'hash' => $contentHash,
            ]);
        }

        return $contentHash;
    }

    public function deleteArticleImage(ArticleImage $articleImage): void
    {
        DoSpacesController::delete($articleImage->filename);

        $articleImage->delete();
    }

    public function updateEmptyImageHashes()
    {
        $articleImages = ArticleImage::whereNull('hash')->get();

        foreach ($articleImages as $articleImage) {
            $filename = $articleImage->filename;

            if ($articleImage->path_url) {
                $filename = basename($articleImage->path_url);
            }

            $content = DoSpacesController::getContent($filename);

            if (!$content) {
                continue;
            }

            $contentHash = md5($content);

            $articleImage->update([
                'filename' => $filename,
                'hash' => $contentHash,
            ]);
        }
    }

	public function getRetailers(Request $request, Article $article)
	{
		$days = (int) $request->get('days', 60);

        $customerNumbers = DB::table('sales_order_lines')
            ->select('customers.customer_number')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->leftJoin('customers', 'customers.external_id', '=', 'sales_orders.customer')
            ->where('sales_orders.date', '>=', date('Y-m-d', strtotime('-' . $days . ' days')))
            ->where('sales_order_lines.article_number', $article->article_number)
            ->groupBy('customers.customer_number')
            ->pluck('customers.customer_number')
            ->toArray();

        $retailers = Customer::whereIn('customer_number', $customerNumbers)
            ->get()
            ->toArray();

        // Always add "Vendora / Lifestylestore" as a retailer
        if (!in_array('vendora', $customerNumbers)) {

            $customer = Customer::where('customer_number', 'vendora')->first();

            if ($customer) {
                $retailers[] = $customer;
            }
        }

        return ApiResponseController::success($retailers);
	}
}
