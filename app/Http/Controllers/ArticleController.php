<?php

namespace App\Http\Controllers;

use App\Actions\DispatchArticleUpdate;
use App\Actions\UploadArticlePackageImage;
use App\Enums\LaravelQueues;
use App\Jobs\CategorizeArticle;
use App\Jobs\GenerateArticleTitles;
use App\Jobs\GenerateFaqForArticle;
use App\Models\Article;
use App\Models\ArticleFaqEntry;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use App\Models\ArticleReview;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReview;
use App\Models\StockItem;
use App\Models\StockKeepTodo;
use App\Models\StockKeepTransaction;
use App\Models\Supplier;
use App\Models\SupplierArticlePrice;
use App\Models\UnspscCategory;
use App\Services\RelatedArticlesService;
use App\Services\ShortDescriptionService;
use App\Services\StockKeepService;
use App\Services\SupplierArticlePriceService;
use App\Services\Todo\TodoItemService;
use App\Services\TranslationServiceManager;
use App\Services\VismaNet\VismaNetArticleService;
use App\Services\WMS\StockItemService;
use App\Utilities\ArticleTitleUtility;
use App\Utilities\ImageBackgroundAnalyzer;
use App\Utilities\WarehouseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function getBrands()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $brands = DB::table('articles')
            ->pluck('brand')
            ->toArray();

        $brands = array_unique($brands);
        $brands = array_filter($brands);

        sort($brands);

        return ApiResponseController::success($brands);
    }

    public function getEditData()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        // Suppliers
        $suppliers = Supplier::all();

        // Brands
        $brands = DB::table('articles')
            ->pluck('brand')
            ->toArray();

        $brands[] = 'myFirst';

        $brands = array_unique($brands);
        $brands = array_filter($brands);

        sort($brands);

        // UNSPSC cateogies
        $unspsc = UnspscCategory::orderBy('commodity_title', 'ASC')->get();

        // Categories
        $categoryController = new ArticleCategoryController();
        $categories = $categoryController->getCategoryTree(
            $categoryController->getAllCategoryIDs()
        );


        return ApiResponseController::success([
            'suppliers' => $suppliers->toArray(),
            'brands' => $brands,
            'unspsc' => $unspsc->toArray(),
            'categories' => $categories,
        ]);
    }

    public function unspscCategories()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categories = UnspscCategory::orderBy('commodity_title', 'ASC')->get();

        return ApiResponseController::success($categories->toArray());
    }

    public function getStockData()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $stockData = DB::table('articles')
            ->select('article_number', 'stock')
            ->where('status', '!=', 'Inactive')
            ->get()
            ->toArray();

        return ApiResponseController::success($stockData);
    }

    public function getSimple(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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

    public function getDataForOrderRow(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleNumber = $request->input('article_number');

        $article = DB::table('articles')
            ->select('id', 'article_number', 'description')
            ->where('article_number', '=', $articleNumber)
            ->first();

        if (!$article) {
            return ApiResponseController::error('Article not found.');
        }

        return ApiResponseController::success([
            'article_number' => $article->article_number,
            'description' => $article->description,
            'unit_price' => 0,
        ]);
    }

    public function getRelateArticlesSuggestions(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleIDs = $request->input('article_ids', []);

        $articles = DB::table('articles')
            ->select('id', 'description', 'shop_description_en')
            ->whereIn('id', $articleIDs)
            ->get();

        $articleRawData = '';
        for ($i = 1;$i <= count($articles);$i++) {
            $articleRawData .= 'JSON-object for article ' . $i . ':' . PHP_EOL . json_encode($articles[$i - 1]) . PHP_EOL . PHP_EOL;
        }

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('group_related_articles');

        $rawResponse = $promptController->execute(
            $prompt->id,
            ['articleData' => $articleRawData]
        );

        $response = json_decode($rawResponse, true);

        if (!isset($response['groups']) || !is_array($response['groups'])) {
            return ApiResponseController::error('Failed to generate suggestions. Please try again.');
        }

        return ApiResponseController::success($response);
    }

    public function getRelateArticles(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleNumbers = $request->input('article_numbers', []);

        $articles = Article::toBase()
            ->select('id', 'article_number', 'description', 'is_single')
            ->whereIn('article_number', $articleNumbers)
            ->get();

        foreach ($articles as &$article) {
            $linkedArticles = DB::table('related_articles')
                ->select('parent_article_id')
                ->where('child_article_id', $article->id)
                ->pluck('parent_article_id');

            $article->related_articles = $linkedArticles ? $linkedArticles->toArray() : [];
        }

        return ApiResponseController::success($articles->toArray());
    }

    public function setMarked(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleIDs = $request->input('article_ids', []);
        $flag = (bool) $request->input('flag');

        if (!$articleIDs) {
            return ApiResponseController::success();
        }

        if ($flag) {
            foreach ($articleIDs as $articleID) {
                DB::table('marked_articles')->updateOrInsert(
                    ['article_id' => $articleID],
                    ['updated_at' => now()]
                );
            }
        } else {
            DB::table('marked_articles')->whereIn('article_id', $articleIDs)->delete();
        }

        return ApiResponseController::success();
    }

    public function relateArticles(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleIDs = $request->input('article_ids', []);

        if ($articleIDs && is_array($articleIDs)) {
            $service = new RelatedArticlesService();
            $service->syncGroup($articleIDs);
        }

        return ApiResponseController::success();
    }

    public function deleteRelateArticles(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleIDs = $request->input('article_ids', []);

        if ($articleIDs && is_array($articleIDs)) {
            $service = new RelatedArticlesService();
            $service->disconnectSubset($articleIDs);
        }

        return ApiResponseController::success();
    }

    public function setSingleArticles(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleIDs = $request->input('article_ids', []);
        $flag = (bool) $request->input('flag');

        DB::table('articles')
            ->whereIn('id', $articleIDs)
            ->update(['is_single' => ($flag ? 1 : 0)]);

        return ApiResponseController::success();
    }

    public function search(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $searchQuery = (string) $request->input('search', '');

        $articles = DB::table('articles')
            ->select(
                'articles.id', 'articles.description', 'articles.article_number', 'articles.ean', 'articles.stock_manageable AS total_stock',
                'image.path_url'
            )
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
            ->where('status', '!=', 'Inactive')
            ->where(function($query) use ($searchQuery) {
                $query->where('article_number', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('ean', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('description', 'LIKE', '%' . $searchQuery . '%');
            })
            ->limit(10)
            ->get();

        $articlesArray = $articles->toArray();

        if ($articlesArray) {
            foreach ($articlesArray as &$article) {
                $article->stock_locations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);
            }
        }

        return ApiResponseController::success($articlesArray);
    }

    public function getUngrouped(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $supplierNumber = $request->input('supplier_number', '');

        $count = Article::where('supplier_number', '=', $supplierNumber)
            ->where('is_single', 0)
            ->whereDoesntHave('linkedChildren')
            ->whereDoesntHave('linkedParents')
            ->count();

        return ApiResponseController::success(['count' => $count]);
    }

    public function getBasic(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        // Get input parameters
        $filters = $request->input('filter');
        $columns = $request->input('columns', ['*']);
        $currency = $request->input('currency');
        $articleNumber = $request->input('article_number');
        $page = $request->input('page', 0);

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

        if ($request->input('only_marked', false)) {
            $markedArticleIDs = DB::table('marked_articles')
                ->pluck('article_id')
                ->toArray();

            $query->whereIn('id', $markedArticleIDs);
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

        if ($articleNumber) {
            $query->orWhere('article_number', $articleNumber);
        }

        $missing = $request->input('missing');
        if ($missing) {
            $column = null;
            $useLocales = false;
            $locales = [];

            switch ($missing) {
                case 'shop_title':
                    $column = 'shop_title';
                    $useLocales = true;
                    break;

                case 'shop_description':
                    $column = 'shop_description';
                    $useLocales = true;
                    break;

                case 'shop_marketing_description':
                    $column = 'shop_marketing_description';
                    $useLocales = true;
                    break;

                case 'short_description':
                    $column = 'short_description';
                    $useLocales = true;
                    break;

                case 'meta_title':
                    $column = 'meta_title';
                    $useLocales = true;
                    break;

                case 'meta_description':
                    $column = 'meta_description';
                    $useLocales = true;
                    break;

                case 'google_category':
                    $column = 'google_product_category';
                    $useLocales = false;
                    break;
            }

            if ($column) {
                if ($useLocales) {
                    $languages = (new LanguageController())->getAllLanguages();
                    $locales = $languages->pluck('language_code');
                }

                $query->where('is_webshop', '1')
                    ->whereIn('status', ['Active', 'NoPurchases']);

                $query->where(function($q) use ($column, $useLocales, $locales) {
                    if ($useLocales) {
                        foreach ($locales as $locale) {
                            $q->orWhere($column . '_' . $locale, '=', '')
                                ->orWhere($column . '_' . $locale, '=', '0')
                                ->orWhereNull($column . '_' . $locale);
                        }
                    } else {
                        $q->orWhere($column, '=', '')
                            ->orWhere($column, '=', '0')
                            ->orWhereNull($column);
                    }
                });
            }
        }

        // Execute query
        $query->orderBy('created_at', 'DESC');

        if ($page > 0) {
            $query->limit(500)->offset(($page - 1) * 500);
        }

        $articles = $query->get()->toArray();

        // Convert article objects into an array
        $articles = array_map(function ($article) {
            return get_object_vars($article);
        }, $articles);

        if ($request->input('outlet_prices') && $articles) {
            foreach ($articles as &$article) {
                $article['outlet_prices'] = Article::getOutletPrices($article['id']);
            }
        }

        if ($request->input('related_articles') && $articles) {

            $relatedArticlesIdentifier = $request->input('related_articles_identifier', 'id');

            foreach ($articles as &$article) {
                $model = Article::with(['linkedChildren', 'linkedParents'])->find($article['id']);

                if ($model) {
                    $article['related_articles'] = $model->linkedChildren
                        ->merge($model->linkedParents)
                        ->pluck($relatedArticlesIdentifier)
                        ->unique()
                        ->values()
                        ->toArray();
                } else {
                    $article['related_articles'] = [];
                }
            }
        }

        if ($request->has('expand_article_name') && $articles) {
            $languages = (new LanguageController())->getAllLanguages();

            foreach ($articles as &$article) {
                foreach ($languages as $language) {
                    $article['article_name_' . $language->language_code] = ArticleTitleUtility::getTitle($article['id'], $language->language_code, false);
                }
            }
        }

        if ($request->has('expand_attributes') && $articles) {
            foreach($articles as &$article) {
                $article['attributes'] = (new Article())->getAttributesArray($article['id']);
            }
        }

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
                        'purchase_orders.date',
                        'purchase_orders.currency_rate',
                    )
                    ->where('purchase_order_lines.article_number', '=', $article['article_number'])
                    ->where('purchase_orders.is_draft', '=', 0)
                    ->orderBy('purchase_orders.date', 'DESC')
                    ->get();

                $salesOrderLines = DB::table('sales_order_lines')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
                    ->select(
                        'sales_orders.date'
                    )
                    ->where('sales_order_lines.article_number', '=', $article['article_number'])
                    ->orderBy('sales_orders.date', 'DESC')
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

                $article['current_cost'] = 0;
                $article['current_cost_SEK'] = 0;

                $supplierPrice = SupplierArticlePrice::where('article_number', $article['article_number'])->first();
                if ($supplierPrice) {
                    $article['current_cost'] = (float) $supplierPrice->price;

                    $currencyConverter = new CurrencyConvertController();
                    $article['current_cost_SEK'] = $currencyConverter->convert($article['current_cost'], $supplierPrice->currency, 'SEK');
                }

                $article['last_cost'] = 0;
                $article['average_cost'] = 0;
                $article['highest_cost'] = 0;
                $article['lowest_cost'] = 0;
                $article['first_seen'] = '';
                $article['last_sale'] = '';

                if ($orderLines && $orderLines->count() > 0) {
                    $mappedOrderLines = $orderLines->map(function ($line) {
                        return $line->unit_cost * ($line->currency_rate ?: 1);
                    });

                    $article['last_cost'] = $orderLines->first()->unit_cost * ($orderLines->first()->currency_rate ?: 1);
                    $article['average_cost'] = round($mappedOrderLines->avg() ?: 0, 2);
                    $article['highest_cost'] = $mappedOrderLines->max() ?: 0;
                    $article['lowest_cost'] = $mappedOrderLines->min() ?: 0;

                    $article['first_seen'] = min(array_column($orderLines->toArray(), 'date'));
                }

                if ($salesOrderLines && $salesOrderLines->count() > 0) {
                    $article['last_sale'] = substr(max(array_column($salesOrderLines->toArray(), 'date')), 0, 10);
                }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $reviews = ArticleReview::where('article_number', $article->article_number)
            ->orderBy('created_at', 'DESC')
            ->get();

        return ApiResponseController::success($reviews->toArray());
    }

    public function getFAQ(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $faqEntries = ArticleFaqEntry::where('article_id', $article->id)
            ->orderBy('created_at', 'ASC')
            ->get();

        return ApiResponseController::success($faqEntries->toArray());
    }

    public function getArticleWmsData(Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleData = DB::table('articles')
            ->select('id', 'article_number', 'description', 'ean', 'stock_manageable')
            ->where('article_number', '=', $article->article_number)
            ->first();

        $articleData = (array) $articleData;

        $articleData['image'] = DB::table('article_images')
            ->select('path_url')
            ->where('article_id', '=', $article->id)
            ->orderBy('list_order', 'ASC')
            ->first()->path_url ?? null;

        $articleData['locations'] = WarehouseHelper::getArticleLocationsWithStock($article->article_number);

        return ApiResponseController::success($articleData);
    }

    public function stockKeepArticle(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $signature = get_display_name();

        try {
            $markForInvestigation = false;

            $stockValues = $request->input('stock_values');
            $stockValues = json_decode($stockValues, true);

            $locations = WarehouseHelper::getArticleLocationsWithStock($article->article_number);
            $existingIdentifiers = array_column($locations, 'identifier');

            foreach ($existingIdentifiers as $identifier) {
                if (isset($stockValues[$identifier])) continue;

                $stockValues[$identifier] = 0;
            }

            if (!$stockValues || !is_array($stockValues)) {
                return ApiResponseController::error('No stock values provided');
            }

            $identifiers = [];
            $values = [];
            $diffs = [];

            $i = 0;
            foreach ($stockValues as $identifier => $quantity) {
                if ($quantity === '' || !is_numeric($quantity)) continue;

                if ($identifier == '--') {
                    $response = $this->stockKeepArticleUnspecified($article, $quantity, $signature);
                }
                else {
                    $response = $this->stockKeepArticlePlace($article, $identifier, $quantity, $signature);
                }

                $identifiers[] = $response['identifier'];
                $values[] = $response['value'];
                $diffs[] = $response['diff'];

                if ($response['diff'] != 0) {
                    $markForInvestigation = true;
                }
            }

            // Save the stock keep transaction
            if (count($identifiers) > 0
                && count($values) > 0
                && count($diffs) > 0) {

                $stockKeepTransaction = StockKeepService::makeTransaction(
                    $article->article_number,
                    implode(',', $identifiers),
                    implode(',', $values),
                    implode(',', $diffs),
                    $markForInvestigation
                );
            }

            // Remove tasks to stock keep this article
            StockKeepTodo::where('type', 'article')
                ->where('reference', $article->article_number)
                ->delete();

            return ApiResponseController::success($stockKeepTransaction->toArray());
        } catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }

    private function stockKeepArticlePlace($article, $identifier, $quantity, $signature)
    {
        $response = [
            'identifier' => $identifier,
            'value' => $quantity,
            'diff' => 0
        ];

        $identifierData = WarehouseHelper::getStockPlaceAndCompartment($identifier);
        if (!$identifierData) {
            return $response;
        }

        $stockItemService = new StockItemService();

        $currentValue = WarehouseHelper::getArticleStockAtLocation($article->article_number, $identifier);
        $diff = $quantity - $currentValue;

        $response['diff'] = $diff;

        // Make stock update if a diff is found
        if ($diff != 0) {
            if ($diff > 0) {
                // Add stock items
                $stockItemService->addStockItem($article->article_number, $diff, $identifierData['stock_place_compartment'], $signature, 'Stock keeping');
            }
            else {
                // Remove stock items
                $stockItems = StockItem::where('article_number', $article->article_number)
                    ->where('stock_place_compartment_id', $identifierData['stock_place_compartment']->id)
                    ->limit(abs($diff))
                    ->get();

                $stockItemService->removeStockItems($stockItems, $signature, 'Stock keeping');
            }
        }

        return $response;
    }

    private function stockKeepArticleUnspecified($article, $quantity, $signature)
    {
        $stockItemsCount = (int) StockItem::where('article_number', $article->article_number)
            ->count();

        $currentStockManageable = (int) DB::table('articles')
            ->select('stock_manageable')
            ->where('article_number', $article->article_number)
            ->value('stock_manageable');

        $stockManageable = $stockItemsCount + $quantity;

        $diff = $stockManageable - $currentStockManageable;

        // Update the manageable stock value
        DB::table('articles')
            ->where('article_number', $article->article_number)
            ->update(['stock_manageable' => $stockManageable]);

        return [
            'identifier' => '--',
            'value' => $quantity,
            'diff' => $diff,
        ];
    }

    public function getCategories(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categories = [];

        if ($article->category_ids && is_array($article->category_ids)) {
            $articleCategoryController = new ArticleCategoryController();
            $categories = $articleCategoryController->getCategoryTree($article->category_ids);
        }

        return ApiResponseController::success($categories);
    }

    public function generateCategories(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $categories = $request->get('categories', '');
        $categories = explode(',', $categories);
        $categories = array_filter($categories);

        $numSuggestions = (int) $request->get('suggestions', 5);

        $languages = (new LanguageController())->getAllLanguages();

        $translationController = new TranslationController();

        $response = ['suggestions' => []];

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('generate_category_suggestions');

        $rawResponse = $promptController->execute(
            $prompt->id,
            [
                'existingCategories' => implode(PHP_EOL, $categories),
                'numSuggestions' => $numSuggestions
            ],
            '',
            'gpt-4o'
        );

        $aiResponse = str_replace('```json', '', $rawResponse);
        $aiResponse = str_replace('```', '', $aiResponse);
        $aiResponse = trim($aiResponse);

        $suggestions = json_decode($aiResponse, true);

        for ($i = 0;$i < $numSuggestions;$i++) {
            $suggestion = [
                'sv' => $suggestions['suggestion_' . ($i + 1)] ?? ''
            ];

            foreach ($languages as $locale) {
                if ($locale->language_code == 'sv') continue;

                $translations = $translationController->translate([$suggestion['sv']], 'sv', $locale->language_code, false);

                $suggestion[$locale->language_code] = $translations[0] ?? '';
            }

            $response['suggestions'][] = $suggestion;
        }


        return ApiResponseController::success($response);
    }

    public function get(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $imageIDs = $request->input('image_ids', []);
        if (is_string($imageIDs)) {
            $imageIDs = explode(',', $imageIDs);
        }

        $changed = false;
        $article = null;

        for ($i = 0;$i < count($imageIDs);$i++) {
            $oldListOrder = ArticleImage::where('id', $imageIDs[$i])->pluck('list_order')->first();

            ArticleImage::where('id', $imageIDs[$i])->update(['list_order' => $i]);

            if ($oldListOrder != $i) {
                $changed = true;

                if (!$article) {
                    $article = Article::find(ArticleImage::where('id', $imageIDs[$i])->value('article_id'));
                }
            }
        }

        if ($changed && $article) {
            (new DispatchArticleUpdate)->execute($article->id, false, [], true);
        }

        return ApiResponseController::success();
    }

    public function getAllImages(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $files = ArticleFile::where('article_id', $article->id)
            ->orderBy('filename', 'ASC')
            ->get();

        return ApiResponseController::success($files->toArray());
    }

    public function uploadFile(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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

        (new DispatchArticleUpdate)->execute($article->id, false, [], true);

        return ApiResponseController::success($articleFile->toArray());
    }

    public function deleteFile(Request $request, Article $article, ArticleFile $articleFile)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        DoSpacesController::delete($articleFile->filename);
        $articleFile->delete();

        (new DispatchArticleUpdate)->execute($article->id, false, [], true);

        return ApiResponseController::success();
    }

    public function getSubData(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $images = ArticleImage::select(
                'id', 'article_id', 'filename', 'path_url', 'size', 'solid_background',
                'list_order', 'hash', 'created_at', 'updated_at'
            )
            ->where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        $files = ArticleFile::where('article_id', $article->id)
            ->orderBy('filename', 'ASC')
            ->get();

        $reviews = ArticleReview::where('article_number', $article->article_number)
            ->orderBy('created_at', 'DESC')
            ->get();

        $faqEntries = ArticleFaqEntry::where('article_id', $article->id)
            ->orderBy('created_at', 'DESC')
            ->get();

        return ApiResponseController::success([
            'images' => $images->toArray(),
            'files' => $files->toArray(),
            'reviews' => $reviews->toArray(),
            'faq_entries' => $faqEntries->toArray()
        ]);
    }

    public function getImagesBasic(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $images = ArticleImage::select(
                'id', 'article_id', 'filename', 'path_url', 'size', 'solid_background',
                'list_order', 'hash', 'created_at', 'updated_at'
            )
            ->where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        return ApiResponseController::success($images->toArray());
    }

    public function getImages(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $images = ArticleImage::where('article_id', $article->id)
            ->orderBy('list_order', 'ASC')
            ->get();

        return ApiResponseController::success($images->toArray());
    }

    public function uploadPackageImages(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $frontImage = $request->file('front');
        $backImage = $request->file('back');

        if (!$frontImage && !$backImage) {
            return ApiResponseController::error('No image uploaded');
        }

        if ($frontImage) {
            $frontImageContent = @file_get_contents($frontImage->getRealPath());
            if ($frontImageContent) {
                (new UploadArticlePackageImage)->execute(
                    $article->id,
                    $frontImage->getClientOriginalName(),
                    $frontImageContent,
                    UploadArticlePackageImage::IMAGE_TYPE_FRONT
                );
            }
        }

        if ($backImage) {
            $backImageContent = @file_get_contents($backImage->getRealPath());
            if ($backImageContent) {
                (new UploadArticlePackageImage)->execute(
                    $article->id,
                    $backImage->getClientOriginalName(),
                    $backImageContent,
                    UploadArticlePackageImage::IMAGE_TYPE_BACK
                );
            }
        }

        return ApiResponseController::success();
    }

    public function uploadImage(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
            'solid_background' => $solidBackground ? 1 : 0,
            'list_order' => $newListOrder,
            'hash' => md5($imageContent),
        ]);

        return ApiResponseController::success($articleImage->toArray());
    }

    public function updateImage(Request $request, Article $article, ArticleImage $articleImage)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleImage->update([
            'solid_background' => (int) $request->input('solid_background', 0),
        ]);

        return ApiResponseController::success($articleImage->toArray());
    }

    public function deleteImage(Request $request, Article $article, ArticleImage $articleImage)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $this->deleteArticleImage($articleImage);

        return ApiResponseController::success();
    }

    public function storeV2(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'article_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $description = $request->input('description');
        $articleName = $request->input('article_name_en');

        if (!$description && !$articleName) {
            return ApiResponseController::error('Missing required fields: description or article_name_en.');
        }

        // Make sure article number is not used
        $articleNumber = $request->article_number;
        if (Article::where('article_number', $articleNumber)->exists()) {
            return ApiResponseController::error('Article number already exists.');
        }

        $fillables = get_model_attributes(Article::class);

        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            $fillables[] = 'article_name_' . $language->language_code;
        }

        $postData = $request->all();

        $storeData = array_intersect_key($postData, array_flip($fillables));
        $storeData = $this->formatPostData($request, $storeData);

        $alternatives = null;
        if (isset($storeData['alternatives'])) {
            $alternatives = $storeData['alternatives'];
            unset($storeData['alternatives']);
        }

        // Handle description/article_name
        if (!isset($storeData['description'])) {
            $storeData['description'] = $storeData['article_name_en'];
        }

        $article = Article::create($storeData);
        if (!($article['id'] ?? 0)) {
            return ApiResponseController::error('Failed to create article.');
        }

        // Store attributes
        foreach ($request->all() as $key => $value) {
            if (!str_starts_with($key, 'attribute_')) continue;

            $key = str_replace('attribute_', '', $key);
            $value = (string) $value;

            $article->storeAttribute($key, $value);
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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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

        // Make sure article number is unique
        if (DB::table('articles')->where('article_number', $data['article_number'])->exists()) {
            return ApiResponseController::error('Article number already exists.');
        }


        $article = Article::create($data);

        // Log the stock
        $stockLogController = new StockLogController();
        $stockLogController->logStock($article->article_number, $article->stock);

        return ApiResponseController::success([$article->toArray()]);
    }

    public function updateMany(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $fillables = get_model_attributes(Article::class);

        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            $fillables[] = 'article_name_' . $language->language_code;
        }

        $updates = $request->all();

        $allowedUpdates = array_intersect_key($updates, array_flip($fillables));
        $allowedUpdates = $this->formatPostData($request, $allowedUpdates);

        // Never allow updating these fields
        unset($allowedUpdates['article_number']);

        $alternatives = null;
        if (isset($allowedUpdates['alternatives'])) {
            $alternatives = $allowedUpdates['alternatives'];
            unset($allowedUpdates['alternatives']);
        }

        // Handle description/article_name
        if (!isset($allowedUpdates['description'])) {
            foreach ($languages as $language) {
                if (!isset($allowedUpdates['article_name_' . $language->language_code])) continue;

                ArticleTitleUtility::setTitle($article, $allowedUpdates['article_name_' . $language->language_code], $language->language_code);

                if ($language->language_code == 'en') {
                    DB::table('articles')
                        ->where('id', $article->id)
                        ->update(['description' => $allowedUpdates['article_name_' . $language->language_code]]);
                }

                unset($allowedUpdates['article_name_' . $language->language_code]);
            }
        }

        // Update attributes
        foreach ($request->all() as $key => $value) {
            if (!str_starts_with($key, 'attribute_')) continue;

            $key = str_replace('attribute_', '', $key);
            $value = (string) $value;

            $article->storeAttribute($key, $value);
        }

        $article->update($allowedUpdates);

        trigger_stock_sync($article->article_number);

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

        if ($request->input('faq_queue', 0)) {
            ArticleFaqEntry::where('article_id', $article->id)->delete();

            GenerateFaqForArticle::dispatch($article)->onQueue(LaravelQueues::DEFAULT->value);
        }

        return ApiResponseController::success([$article->toArray()]);
    }

    public function customerReviews()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $customerReviews = CustomerReview::query()->orderBy('created_at', 'DESC')->get();

        if ($customerReviews) {
            foreach ($customerReviews as &$customerReview) {
                $customerReview->brand = '';

                $article = Article::where('article_number', $customerReview->article_number)->first();
                if ($article) {
                    $customerReview->brand = ($article->supplier->brand_name ?? '');
                }
            }
        }

        return ApiResponseController::success($customerReviews->toArray());
    }

    public function customerReviewsStore(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        CustomerReview::create([
            'article_number' => (string) $request->input('article_number'),
            'rating' => (int) $request->input('rating'),
            'name' => (string) $request->input('name'),
            'review' => (string) $request->input('review'),
            'locale' => (string) ($request->input('locale') ?: 'en'),
        ]);

        return ApiResponseController::success();
    }

    public function customerReviewsUpdate(Request $request, CustomerReview $customerReview)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $customerReview->update([
            'article_number' => (string) $request->input('article_number'),
            'rating' => (int) $request->input('rating'),
            'name' => (string) $request->input('name'),
            'review' => (string) $request->input('review'),
        ]);

        return ApiResponseController::success();
    }

    public function customerReviewsDelete(Request $request, CustomerReview $customerReview)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $customerReview->delete();

        return ApiResponseController::success();
    }
    public function getArticle(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return ApiResponseController::success($article->toArray());
    }

    public function update(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $fillables = get_model_attributes(Article::class);

        $updates = $request->all();

        // Never allow updating these fields
        unset($updates['article_number']);

        $allowedUpdates = array_intersect_key($updates, array_flip($fillables));

        $article->update($allowedUpdates);

        // Update supplier price
        if (isset($updates['current_cost'])) {
            $supplier = $article->supplier;
            if ($supplier->id ?? 0) {

                $supplierPriceService = new SupplierArticlePriceService();
                $supplierPriceService->createSupplierArticlePrice([
                    'article_number' => (string) $article->article_number,
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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        DoSpacesController::delete($articleImage->filename);

        $articleImage->delete();
    }

    public function updateEmptyImageHashes()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
	    if ($this->shouldLogControllerMethod()) {

	        $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

	        action_log('Invoked controller method.', $__controllerLogContext);

	    }

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

    public function createStockKeepTodo(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $hasTodo = StockKeepTodo::where('type', 'article')
            ->where('reference', $article->article_number)
            ->exists();

        if ($hasTodo) {
            return ApiResponseController::error('Stock keep todo already exists');
        }

        StockKeepService::makeTodo(
            $article->article_number,
            StockKeepService::TODO_TYPE_ARTICLE
        );

        return ApiResponseController::success();
    }

    public function createMeasurementTodo(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $service = new TodoItemService();
        $service->createCollectArticle(
            $article->id,
            'size',
            0,
            'system'
        );

        return ApiResponseController::success();
    }

    public function getGoogleProductCategory(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new CategorizeArticle($article, true);
        $categoryID = $job->handle();

        return ApiResponseController::success([
            'category_id' => $categoryID,
        ]);
    }

    public function getNewShortTitle(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleShortTitle(true);

        return ApiResponseController::success([
            'value' => ($updates['description'] ?? '')
        ]);
    }

    public function getNewShopTitle(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleLongTitle(true);

        return ApiResponseController::success([
            'value' => ($updates['shop_title_en'] ?? '')
        ]);
    }

    public function getNewColor(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleColor(true);

        return ApiResponseController::success([
            'value' => ($updates['color_en'] ?? '')
        ]);
    }

    public function getNewMarketingDescription(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handlePremiumIntroText(true);

        return ApiResponseController::success([
            'value' => ($updates['shop_marketing_description_en'] ?? '')
        ]);
    }

    public function getNewShortDescription(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleSellingPoints(true);

        return ApiResponseController::success([
            'value' => ($updates['short_description_en'] ?? '')
        ]);
    }

    public function getNewMetaTitle(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleMetaTitle(true);

        return ApiResponseController::success([
            'value' => ($updates['meta_title_en'] ?? '')
        ]);
    }

    public function getNewMetaDescription(Request $request, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $job = new GenerateArticleTitles($article);
        $updates = $job->handleMetaDescription(true);

        return ApiResponseController::success([
            'value' => ($updates['meta_description_en'] ?? '')
        ]);
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
                'ean_inner_box',
                'ean_master_box',
                'upc_code',
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
                'predecessor',
                'outlet_price_mode',
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
                'is_outlet',
                'outlet_price',
                'outlet_max_price',
                'outlet_price_fixed',
                'outlet_inner_price_fixed',
                'outlet_master_price_fixed',
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

        if ($data['created_at'] ?? '') {
            $data['created_at'] = date('Y-m-d H:i:s', strtotime($data['created_at']));
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
            if (array_key_exists('article_name_' . $locale->language_code, $data)) {
                $data['article_name_' . $locale->language_code] = (string) $data['article_name_' . $locale->language_code];
            }
            if (array_key_exists('shop_title_' . $locale->language_code, $data)) {
                $data['shop_title_' . $locale->language_code] = (string) $data['shop_title_' . $locale->language_code];
            }
            if (array_key_exists('shop_description_' . $locale->language_code, $data)) {
                $data['shop_description_' . $locale->language_code] = (string) $data['shop_description_' . $locale->language_code];
            }
            if (array_key_exists('short_description_' . $locale->language_code, $data)) {
                $data['short_description_' . $locale->language_code] = (string) $data['short_description_' . $locale->language_code];
            }
            if (array_key_exists('shop_marketing_description_' . $locale->language_code, $data)) {
                $data['shop_marketing_description_' . $locale->language_code] = (string) $data['shop_marketing_description_' . $locale->language_code];
            }
            if (array_key_exists('meta_title_' . $locale->language_code, $data)) {
                $data['meta_title_' . $locale->language_code] = (string) $data['meta_title_' . $locale->language_code];
            }
            if (array_key_exists('meta_description_' . $locale->language_code, $data)) {
                $data['meta_description_' . $locale->language_code] = (string) $data['meta_description_' . $locale->language_code];
            }
            if (array_key_exists('announcement_' . $locale->language_code, $data)) {
                $data['announcement_' . $locale->language_code] = (string) $data['announcement_' . $locale->language_code];
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
