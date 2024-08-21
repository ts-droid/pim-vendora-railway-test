<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use App\Models\ArticleReview;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Supplier;
use App\Models\SupplierArticlePrice;
use App\Models\UnspscCategory;
use App\Services\SupplierArticlePriceService;
use App\Services\TranslationServiceManager;
use App\Services\VismaNet\VismaNetArticleService;
use App\Utilities\ImageBackgroundAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function getBrands()
    {
        $brands = DB::table('articles')
            ->pluck('brand')
            ->toArray();

        $brands = array_unique($brands);
        $brands = array_filter($brands);

        sort($brands);

        return ApiResponseController::success($brands);
    }

    public function unspscCategories()
    {
        $categories = UnspscCategory::orderBy('commodity_title', 'ASC')->get();

        return ApiResponseController::success($categories->toArray());
    }

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

        $translationServiceID = translation_service();

        if ($articles) {
            foreach ($articles as &$article) {

                // Use different translations?
                if ($translationServiceID) {
                    foreach ($article as $key => $value) {
                        if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
                            $languageCode = $matches[1];
                            $translation = TranslationServiceManager::getTranslation('articles', 'shop_title', $article->id, $languageCode, $translationServiceID);
                            if ($translation) {
                                $article->{$key} = $translation->translation;
                            }
                        }
                        else if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
                            $languageCode = $matches[1];
                            $translation = TranslationServiceManager::getTranslation('articles', 'shop_description', $article->id, $languageCode, $translationServiceID);
                            if ($translation) {
                                $article->{$key} = $translation->translation;
                            }
                        }
                    }
                }

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

        if (!in_array('*', $columns) && !in_array('created_at', $columns)) {
            $columns[] = 'created_at';
        }

        // Build query
        $query = DB::table('articles')
            ->select($columns);

        if ($filters) {
            foreach ($filters as $filter) {
                $count = count($filter);

                if (is_array($filter[0])) {
                    $query->where(function($query) use ($filter) {
                        foreach ($filter as $subFilter) {
                            $subCount = count($subFilter);

                            if ($subCount === 3) {
                                $query->orWhere($subFilter[0], $subFilter[1], $subFilter[2]);
                            }
                            else if ($subCount === 2) {
                                $query->orWhereIn($subFilter[0], $subFilter[1]);
                            }
                        }
                    });
                }
                else if ($count === 3) {
                    $query->where($filter[0], $filter[1], $filter[2]);
                }
                elseif ($count === 2) {
                    $query->whereIn($filter[0], $filter[1]);
                }
            }
        }

        if ($request->input('only_uncompleted')) {
            $languageCodes = [];
            foreach ((new LanguageController())->getAllLanguages() as $language) {
                $languageCodes[] = $language->language_code;
            }

            $currencies = CurrencyController::SUPPORTED_CURRENCIES;

            $query->where(function($query) use($languageCodes, $currencies) {
                $query->where('is_webshop', '=', 0);

                foreach ($languageCodes as $language) {
                    $query->orWhere('shop_title_' . $language, '=', '');
                    $query->orWhere('shop_description_' . $language, '=', '');
                }

                foreach ($currencies as $currency) {
                    $query->orWhere('rek_price_' . $currency, '=', 0);
                }

            });

            $query->whereIn('status', ['Active', 'NoPurchases']);
        }

        // Execute query
        $articles = $query->orderBy('created_at', 'DESC')->get()->toArray();

        // Convert article objects into an array
        $articles = array_map(function ($article) {
            return get_object_vars($article);
        }, $articles);

        // Use different translations?
        $translationServiceID = translation_service();
        if ($translationServiceID) {
            foreach ($articles as &$article) {
                foreach ($article as $key => $value) {
                    if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
                        $languageCode = $matches[1];
                        $translation = TranslationServiceManager::getTranslation('articles', 'shop_title', $article['id'], $languageCode, $translationServiceID);
                        if ($translation) {
                            $article[$key] = $translation->translation;
                        }
                    }
                    else if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
                        $languageCode = $matches[1];
                        $translation = TranslationServiceManager::getTranslation('articles', 'shop_description', $article['id'], $languageCode, $translationServiceID);
                        if ($translation) {
                            $article[$key] = $translation->translation;
                        }
                    }
                }
            }
        }

        if ($request->input('supplier_stats')) {
            foreach ($articles as &$article) {
                if (!isset($article['article_number'])) {
                    continue;
                }

                $orderLines = DB::table('purchase_order_lines')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                    ->select(
                        'purchase_order_lines.unit_cost',
                        'purchase_order_lines.promised_date',
                        'purchase_order_lines.is_completed',
                        'purchase_orders.date'
                    )
                    ->where('purchase_order_lines.article_number', '=', $article['article_number'])
                    ->where('purchase_orders.is_draft', '=', 0)
                    ->orderBy('purchase_orders.date', 'DESC')
                    ->get();

                $filteredOrderLines = $orderLines->filter(function ($item) {
                    return $item->date !== $item->promised_date;
                });

                $totalDays = $filteredOrderLines->reduce(function ($carry, $item) {
                    $date1 = \Carbon\Carbon::parse($item->date);
                    $date2 = \Carbon\Carbon::parse($item->promised_date);
                    $difference = $date1->diffInDays($date2);
                    return $carry + $difference;
                }, 0);

                $etaShipment = 0;
                foreach ($orderLines as $orderLine) {
                    if ($orderLine->is_completed || $orderLine->promised_date <= date('Y-m-d')) {
                        continue;
                    }

                    $date1 = \Carbon\Carbon::parse(date('Y-m-d'));
                    $date2 = \Carbon\Carbon::parse($orderLine->promised_date);
                    $rowEta = $date1->diffInDays($date2);

                    if (!$etaShipment || $rowEta < $etaShipment) {
                        $etaShipment = $rowEta;
                    }
                }

                $article['current_cost'] = (float) SupplierArticlePrice::where('article_number', $article['article_number'])
                    ->pluck('price')
                    ->first();

                $article['last_cost'] = $orderLines->first()->unit_cost ?? 0;
                $article['average_cost'] = round($orderLines->avg('unit_cost') ?: 0, 2);
                $article['highest_cost'] = $orderLines->max('unit_cost') ?: 0;
                $article['lowest_cost'] = $orderLines->min('unit_cost') ?: 0;
                $article['lead_time'] = round($filteredOrderLines->count() > 0 ? $totalDays / $filteredOrderLines->count() : 0);
                $article['eta_shipment'] = $etaShipment;
            }
        }

        // Convert results to requested currency
        if ($currency && $articles) {
            $currencyConverter = new CurrencyConvertController();

            foreach ($articles as &$article) {
                $currencyConverter->convertArray(
                    $article,
                    ['cost_price_avg', 'external_cost', 'last_cost', 'average_cost', 'highest_cost', 'lowest_cost'],
                    'SEK',
                    $currency,
                    date('Y-m-d')
                );
            }
        }

        return ApiResponseController::success($articles);
    }

    public function getReviews(Request $request, Article $article)
    {
        $reviews = ArticleReview::where('article_number', $article->article_number)
            ->orderBy('created_at', 'DESC')
            ->get();

        return ApiResponseController::success($reviews->toArray());
    }

    public function getCategories(Request $request, Article $article)
    {
        $categories = [];

        if ($article->category_ids && is_array($article->category_ids)) {
            $articleCategoryController = new ArticleCategoryController();
            $categories = $articleCategoryController->getCategoryTree($article->category_ids);
        }

        return ApiResponseController::success($categories);
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

    public function setImageListOrder(Request $request)
    {
        $imageIDs = $request->input('image_ids', []);
        if (is_string($imageIDs)) {
            $imageIDs = explode(',', $imageIDs);
        }

        for ($i = 0;$i < count($imageIDs);$i++) {
            ArticleImage::where('id', $imageIDs[$i])->update(['list_order' => $i]);
        }

        return ApiResponseController::success();
    }

    public function getAllImages(Request $request)
    {
        $supplierID = (int) $request->get('supplier_id', 0);
        $articleNumber = $request->get('article_number', '');

        if (!$supplierID && !$articleNumber) {
            return ApiResponseController::success();
        }

        $supplier = null;
        if ($supplierID) {
            $supplier = Supplier::find($supplierID);
        }

        $query = DB::table('articles')
            ->select('id');

        if ($supplier) {
            $query->where('supplier_number', $supplier->number);
        }
        if ($articleNumber) {
            $query->where('article_number', $articleNumber);
        }

        $articleIDs = $query->pluck('id')->toArray();

        $images = ArticleImage::whereIn('article_id', $articleIDs)->get();

        return ApiResponseController::success($images->toArray());
    }

    public function getFiles(Request $request, Article $article)
    {
        $files = ArticleFile::where('article_id', $article->id)
            ->orderBy('filename', 'ASC')
            ->get();

        return ApiResponseController::success($files->toArray());
    }

    public function uploadFile(Request $request, Article $article)
    {
        if (!$request->hasFile('file')) {
            return ApiResponseController::error('No file uploaded');
        }

        $file = $request->file('file');

        $fileContent = @file_get_contents($file->getRealPath());
        if (!$fileContent) {
            return ApiResponseController::error('Error reading file content');
        }

        $remoteFilename = DoSpacesController::store($file->getClientOriginalName(), $fileContent, true);

        $articleFile = ArticleFile::create([
            'article_id' => $article->id,
            'filename' => $remoteFilename,
            'path_url' => DoSpacesController::getURL($remoteFilename),
            'size' => DoSpacesController::getSize($remoteFilename)
        ]);

        // Send the file to WGR
        $wgrController = new WgrController();

        $wgrArticle = $wgrController->getArticle($article->article_number);
        if ($wgrArticle) {
            $fileData = [
                'productID' => $wgrArticle['productId'],
                'base64' => base64_encode($fileContent),
                'filename' => $remoteFilename,
            ];

            foreach (LanguageController::SUPPORTED_EXTERNAL_LANGUAGES['wgr'] as $languageCode) {
                $fileData['title_' . $languageCode] = basename($file->getClientOriginalName());
            }

            $response = $wgrController->makeRequest('ProductFile.create', $fileData);

            $articleFile->update([
                'wgr_id' => (int) ($response[0]['result'] ?? 0)
            ]);
        }

        return ApiResponseController::success($articleFile->toArray());
    }

    public function deleteFile(Request $request, Article $article, ArticleFile $articleFile)
    {
        DoSpacesController::delete($articleFile->filename);

        // Delete file in WGR
        if ($articleFile->wgr_id) {
            $wgrController = new WgrController();
            $wgrController->makeRequest('ProductFile.delete', [
                'id' => $articleFile->wgr_id,
            ]);
        }

        $articleFile->delete();

        return ApiResponseController::success();
    }

    public function getImages(Request $request, Article $article)
    {
        $images = ArticleImage::where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        return ApiResponseController::success($images->toArray());
    }

    public function uploadImage(Request $request, Article $article)
    {
        if (!$request->hasFile('image')) {
            return ApiResponseController::error('No image uploaded');
        }

        $image = $request->file('image');

        $imageContent = @file_get_contents($image->getRealPath());
        if (!$imageContent) {
            return ApiResponseController::error('Error reading image content');
        }

        $newListOrder = (int) ArticleImage::where('article_id', $article->id)
            ->max('list_order') + 1;

        $remoteFilename = DoSpacesController::store($image->getClientOriginalName(), $imageContent, true);

        $imageSize = DoSpacesController::getSize($remoteFilename);

        $solidBackground = ImageBackgroundAnalyzer::hasSolidBackgroundAdvanced($imageContent);

        $articleImage = ArticleImage::create([
            'article_id' => $article->id,
            'filename' => $remoteFilename,
            'path_url' => DoSpacesController::getURL($remoteFilename),
            'size' => $imageSize,
            'solid_background' => $solidBackground,
            'list_order' => $newListOrder,
            'hash' => md5($imageContent),
        ]);

        return ApiResponseController::success($articleImage->toArray());
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

    public function updateImageSolid(Request $request, Article $article, ArticleImage $articleImage)
    {
        $articleImage->update([
            'solid_background' => (int) $request->input('solid_background', 0),
        ]);

        return ApiResponseController::success($articleImage->toArray());
    }

    public function deleteImage(Request $request, Article $article, ArticleImage $articleImage)
    {
        $this->deleteArticleImage($articleImage);

        return ApiResponseController::success();
    }

    public function storeV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'article_number' => 'required|string',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        // Make sure article number is not used
        $articleNumber = $request->article_number;
        if (Article::where('article_number', $articleNumber)->exists()) {
            return ApiResponseController::error('Article number already exists.');
        }

        $fillables = get_model_attributes(Article::class);

        $postData = $request->all();

        $storeData = array_intersect_key($postData, array_flip($fillables));
        $storeData = $this->formatPostData($request, $storeData);

        $alternatives = null;
        if (isset($storeData['alternatives'])) {
            $alternatives = $storeData['alternatives'];
            unset($storeData['alternatives']);
        }

        $article = Article::create($storeData);
        if (!($article['id'] ?? 0)) {
            return ApiResponseController::error('Failed to create article.');
        }

        if ($alternatives !== null) {
            $this->updateAlternatives($article, $alternatives);
        }

        if (isset($postData['current_cost'])) {
            $supplier = $article->supplier;
            if ($supplier->id ?? 0) {

                $supplierPriceService = new SupplierArticlePriceService();
                $supplierPriceService->createSupplierArticlePrice([
                    'article_number' => (string) $article['article_number'],
                    'price' => (float) $postData['current_cost'],
                    'currency' => (string) $supplier->currency,
                ]);

            }
        }

        return ApiResponseController::success($article->toArray());
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

            $vismaNetArticleService = new VismaNetArticleService();

            foreach ($articles as $article) {
                $articleNumber = (string) $article['article_number'] ?? '';

                if (!$articleNumber) {
                    continue;
                }

                $fillables = get_model_attributes(Article::class);
                $allowedUpdates = array_intersect_key($article, array_flip($fillables));

                Article::where('article_number', $articleNumber)->update($allowedUpdates);

                $article = Article::where('article_number', $articleNumber)->first();

                $vismaNetArticleService->updateArticle($article);
            }
        }

        return ApiResponseController::success();
    }

    public function updateV2(Request $request, Article $article)
    {
        $fillables = get_model_attributes(Article::class);

        $updates = $request->all();

        $allowedUpdates = array_intersect_key($updates, array_flip($fillables));
        $allowedUpdates = $this->formatPostData($request, $allowedUpdates);

        $alternatives = null;
        if (isset($allowedUpdates['alternatives'])) {
            $alternatives = $allowedUpdates['alternatives'];
            unset($allowedUpdates['alternatives']);
        }

        $article->update($allowedUpdates);

        if ($alternatives !== null) {
            $this->updateAlternatives($article, $alternatives);
        }

        // Update supplier price
        if (isset($updates['current_cost'])) {
            $supplier = $article->supplier;
            if ($supplier->id ?? 0) {

                $supplierPriceService = new SupplierArticlePriceService();
                $supplierPriceService->createSupplierArticlePrice([
                    'article_number' => (string) $article['article_number'],
                    'price' => (float) $updates['current_cost'],
                    'currency' => (string) $supplier->currency,
                ]);

            }
        }

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        return ApiResponseController::success([$article->toArray()]);
    }

    public function getArticle(Request $request, Article $article)
    {
        return ApiResponseController::success($article->toArray());
    }

    public function update(Request $request, Article $article)
    {
        $fillables = get_model_attributes(Article::class);

        $updates = $request->all();

        $allowedUpdates = array_intersect_key($updates, array_flip($fillables));

        $article->update($allowedUpdates);

        // Update supplier price
        if (isset($updates['current_cost'])) {
            $supplier = $article->supplier;
            if ($supplier->id ?? 0) {

                $supplierPriceService = new SupplierArticlePriceService();
                $supplierPriceService->createSupplierArticlePrice([
                    'article_number' => (string) $article['article_number'],
                    'price' => (float) $updates['current_cost'],
                    'currency' => (string) $supplier->currency,
                ]);

            }
        }

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
            $solidBackground = ImageBackgroundAnalyzer::hasSolidBackgroundAdvanced($imageContent);

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

    private function formatPostData(Request $request, array $data)
    {
        $castTypes = [
            'string' => [
                'status',
                'article_number',
                'description',
                'origin_country',
                'ean',
                'wright_article_number',
                'supplier_number',
                'article_type',
                'brand',
                'eu_import_marking',
                'gtin_inner_box',
                'gtin_master_box',
                'gtin_pallet',
                'google_product_category',
                'unspsc_categories',
                'recommended_replacement_article',
                'hs_code',
                'un_code',
                'minimum_order_quantity',
                'alternatives',
            ],
            'int' => [
                'pallet_height',
                'weight',
                'product_weight',
                'inner_box_weight',
                'master_box_weight',
                'pallet_weight',
                'inner_box',
                'master_box',
                'pallet_size',
                'package_weight_paper',
                'package_weight_plastic',
                'package_weight_metal',
                'package_weight_glass',
                'is_webshop',
                'serial_number_management',
                'is_backorder',
                'is_dropship',
            ],
            'float' => [
                'standard_reseller_margin',
                'minimum_margin',
                'height',
                'width',
                'depth',
                'product_height',
                'product_width',
                'product_depth',
                'inner_box_height',
                'inner_box_width',
                'inner_box_depth',
                'master_box_height',
                'master_box_width',
                'master_box_depth'
            ],
        ];

        foreach ($castTypes as $type => $fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                $value = $data[$field];

                switch ($type) {
                    case 'string':
                        $value = (string) $value;
                        break;

                    case 'int':
                        $value = (int) $value;
                        break;

                    case 'float':
                        $value = (float) $value;
                        break;
                }

                $data[$field] =  $value;
            }
        }

        if (($data['unspsc_categories'] ?? '') === 'new') {
            $unspscID = 0;

            $commodity = (string) $request->unspsc_id;
            $commodityTitle = (string) $request->unspsc_title;

            if ($commodity && $commodityTitle) {
                $unspscCategory = DB::table('unspsc_categories')
                    ->where('commodity', $commodity)
                    ->first();

                if (!$unspscCategory) {
                    $unspscID = DB::table('unspsc_categories')
                        ->insertGetId([
                            'commodity' => $commodity,
                            'commodity_title' => $commodityTitle,
                        ]);
                }
                else {
                    $unspscID = $unspscCategory->id;
                }
            }

            $data['unspsc_categories'] = (string) $unspscID;
        }

        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $locale) {
            if (array_key_exists('shop_title_' . $locale->language_code, $data)) {
                $data['shop_title_' . $locale->language_code] = (string) $data['shop_title_' . $locale->language_code];
            }
            if (array_key_exists('shop_description_' . $locale->language_code, $data)) {
                $data['shop_description_' . $locale->language_code] = (string) $data['shop_description_' . $locale->language_code];
            }
            if (array_key_exists('meta_title_' . $locale->language_code, $data)) {
                $data['meta_title_' . $locale->language_code] = (string) $data['meta_title_' . $locale->language_code];
            }
            if (array_key_exists('meta_description_' . $locale->language_code, $data)) {
                $data['meta_description_' . $locale->language_code] = (string) $data['meta_description_' . $locale->language_code];
            }
        }

        foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
            if (array_key_exists('rek_price_' . $currency, $data)) {
                $data['rek_price_' . $currency] = (float) $data['rek_price_' . $currency];
            }
        }

        return $data;
    }

    private function updateAlternatives(Article $article, string $alternatives)
    {
        $oldAlternatives = $this->alternativesToArray($article->alternatives);
        $alternatives = $this->alternativesToArray($alternatives);

        // Connect this article as alternative to the other articles
        foreach ($alternatives as $articleNumber) {
            $subArticle = Article::where('article_number', $articleNumber)->first();
            if (!$subArticle) {
                // Remove from the list
                $alternatives = array_diff($alternatives, [$articleNumber]);
                continue;
            }

            $articleAlternatives = $this->alternativesToArray($subArticle->alternatives);
            $articleAlternatives[] = $article->article_number;

            $articleAlternatives = array_unique($articleAlternatives);

            $subArticle->update([
                'alternatives' => implode("\n", $articleAlternatives),
            ]);
        }

        // Disconnect removed alternatives between each other
        $removedAlternatives = array_diff($oldAlternatives, $alternatives);
        foreach ($removedAlternatives as $articleNumber) {
            $subArticle = Article::where('article_number', $articleNumber)->first();
            if (!$subArticle) {
                continue;
            }

            $articleAlternatives = $this->alternativesToArray($subArticle->alternatives);
            $articleAlternatives = array_diff($articleAlternatives, [$article->article_number]);

            $subArticle->update([
                'alternatives' => implode("\n", $articleAlternatives),
            ]);
        }

        $article->update([
            'alternatives' => implode("\n", $alternatives),
        ]);
    }

    private function alternativesToArray($alternatives)
    {
        // Split new lines into array
        $alternatives = preg_split("/\r\n|\n|\r/", $alternatives);

        // Trim and filter
        $alternatives = array_map('trim', $alternatives);
        $alternatives = array_filter($alternatives);

        return $alternatives;
    }
}
