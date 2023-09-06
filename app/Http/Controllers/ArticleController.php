<?php

namespace App\Http\Controllers;

use App\Events\ArticleUpdated;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
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

        // Convert results to requested currency
        $convertedCurrency = $request->get('covert_to_currency', '');
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
        ];

        foreach (LanguageController::SUPPORTED_LANGUAGES as $locale) {
            $data['shop_title_' . $locale] = (string) ($request->{'shop_title_' . $locale} ?? '');
            $data['shop_description_' . $locale] = (string) ($request->{'shop_description_' . $locale} ?? '');
            $data['meta_title_' . $locale] = (string) ($request->{'meta_title_' . $locale} ?? '');
            $data['meta_description_' . $locale] = (string) ($request->{'meta_description_' . $locale} ?? '');
        }

        $article = Article::create($data);

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

        $changes = $article->getChanges();

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

        // Dispatch updated event
        ArticleUpdated::dispatch($article, $changes);

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

	public function getRetailers(Request $request, Article $article)
	{
		$days = (int) $request->get('days', 60);

		// Fetch invoices within the period
		$invoices = CustomerInvoice::where('date', '>=', date('Y-m-d', strtotime('-' . $days . ' days')))
			->get();

		if (!$invoices) {
			return ApiResponseController::success([]);
		}

        $customerNumbers = [];
		$retailers = [];

		foreach ($invoices as $invoice) {
            // Skip invoice if customer is already found
            if (in_array($invoice->customer_number, $customerNumbers)) {
                continue;
            }

			foreach ($invoice->lines as $invoiceLine) {
				if ($invoiceLine->article_number == $article->article_number) {
					// Matching article found, this customer is a retailer

					$customer = Customer::where('customer_number', $invoice->customer_number)->first();

					if ($customer) {
                        $customerNumbers[] = $invoice->customer_number;
						$retailers[] = $customer->toArray();
					}

					continue 2;
				}
			}
		}

        // Always add "Vendora / Lifestylestore" as a retailer
        if (!in_array('vendora', $customerNumbers)) {

            $customer = Customer::where('customer_number', 'vendora')->first();

            if ($customer) {
                $customerNumbers[] = 'vendora';
                $retailers[] = $customer;
            }
        }

		return ApiResponseController::success($retailers);
	}

    public function calculateSalesVolume()
    {
        $articles = Article::all();

        if (!$articles) {
            return;
        }

        // Load all invoices within the last 30 days and summarize the sales per article
        $articlesSummary = [];

        $invoices = CustomerInvoice::where('date', '>=', date('Y-m-d', strtotime('-30 days')))
            ->get();

        foreach (($invoices ?: []) as $invoice) {
            foreach (($invoice->lines ?: []) as $invoiceLine) {
                if (!isset($articlesSummary[$invoiceLine->article_number])) {
                    $articlesSummary[$invoiceLine->article_number] = [
                        'quantity' => 0,
                    ];
                }

                $articlesSummary[$invoiceLine->article_number]['quantity'] += $invoiceLine->quantity;
            }
        }

        // Update each article using the above summary
        foreach ($articles as $article) {
            $articleSummary = $articlesSummary[$article->article_number] ?? null;

            if (!$articleSummary) {
                continue;
            }

            $article->sales_30_days = $articleSummary['quantity'];
            $article->save();
        }
    }
}
