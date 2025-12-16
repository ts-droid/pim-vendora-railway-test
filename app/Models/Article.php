<?php

namespace App\Models;

use App\Actions\DispatchArticleUpdate;
use App\Http\Controllers\CurrencyController;
use App\Services\ArticleQuantityCalculator;
use App\Services\EcbService;
use App\Services\SupplierArticlePriceService;
use App\Services\TranslationServiceManager;
use App\Utilities\ArticleTitleUtility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Article extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
        'updated_at',
    ];

    protected $casts = [
        'category_ids' => 'array',
        'last_saved' => 'array'
    ];

    protected $appends = [
        'stock_incoming',
        'stock_on_order',
        'stock_net',
        'stock_time',
        'sales_per_month', // Average sales per month last 180 days
        'sales_per_month_1', // Sales per month last 30 days
        'sales_per_month_2', // Sales per month last 30-60 days
        'sales_per_month_3', // Sales per month last 60-90 days
        'purchase_price',
        'purchase_price_currency',
    ];

    protected static function booted()
    {
        static::retrieved(function ($article) {
            $article->shop_title_sv = $article->getAttribute('shop_title_sv');
            $article->shop_title_fi = $article->getAttribute('shop_title_fi');
            $article->shop_title_en = $article->getAttribute('shop_title_en');
            $article->shop_title_no = $article->getAttribute('shop_title_no');
            $article->shop_title_da = $article->getAttribute('shop_title_da');

            $article->shop_short_description_sv = $article->getAttribute('shop_short_description_sv');
            $article->shop_short_description_fi = $article->getAttribute('shop_short_description_fi');
            $article->shop_short_description_en = $article->getAttribute('shop_short_description_en');
            $article->shop_short_description_no = $article->getAttribute('shop_short_description_no');
            $article->shop_short_description_da = $article->getAttribute('shop_short_description_da');

            $article->shop_description_sv = $article->getAttribute('shop_description_sv');
            $article->shop_description_fi = $article->getAttribute('shop_description_fi');
            $article->shop_description_en = $article->getAttribute('shop_description_en');
            $article->shop_description_no = $article->getAttribute('shop_description_no');
            $article->shop_description_da = $article->getAttribute('shop_description_da');

            $supplierPriceService = new SupplierArticlePriceService();
            $supplierPrice = $supplierPriceService->getSupplierArticlePrice($article->article_number);

            $article->stock_incoming = ArticleQuantityCalculator::getIncoming($article->article_number);
            $article->stock_on_order = ArticleQuantityCalculator::getOnOrder($article->article_number);
            $article->stock_net = ArticleQuantityCalculator::getNetStock($article->article_number);
            $article->stock_time = ArticleQuantityCalculator::getStockTime($article->article_number);

            $article->sales_per_month = ArticleQuantityCalculator::getSalesPerMonth(
                $article->article_number,
                date('Y-m-d', strtotime('-6 months')),
                date('Y-m-d')
            );

            $article->sales_per_month_1 = ArticleQuantityCalculator::getSalesPerMonth(
                $article->article_number,
                date('Y-m-d', strtotime('-1 months')),
                date('Y-m-d')
            );

            $article->sales_per_month_2 = ArticleQuantityCalculator::getSalesPerMonth(
                $article->article_number,
                date('Y-m-d', strtotime('-2 months')),
                date('Y-m-d', strtotime('-1 months')),
            );

            $article->sales_per_month_3 = ArticleQuantityCalculator::getSalesPerMonth(
                $article->article_number,
                date('Y-m-d', strtotime('-3 months')),
                date('Y-m-d', strtotime('-2 months')),
            );

            $article->purchase_price = $supplierPrice->price ?? 0;
            $article->purchase_price_currency = $supplierPrice->currency ?? '';
        });

        static::updating(function ($article) {
            foreach ($article->getAppends() as $append) {
                unset($article->attributes[$append]);
            }

            $lastSaved = $article->last_saved ?: [];
            $dirtyColumns = array_keys($article->getDirty());

            $now = now()->format('Y-m-d H:i:s');
            foreach ($dirtyColumns as $column) {
                $lastSaved[$column] = $now;
            }

            $article->last_saved = $lastSaved;
        });

        static::updated(function ($article) {
            $changes = $article->getChanges();

            if (isset($changes['description'])) {
                ArticleTitleUtility::translateTitles($article);
            }

            (new DispatchArticleUpdate)->execute($article->id, false, $changes);
        });

        static::created(function ($article) {
            ArticleTitleUtility::translateTitles($article);
            (new DispatchArticleUpdate)->execute($article->id, true, []);
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_number', 'number');
    }

    public function stock_logs()
    {
        return $this->hasMany(StockLog::class, 'article_number', 'article_number');
    }

    public function faqEntries()
    {
        return $this->hasMany(ArticleFaqEntry::class, 'article_id', 'id');
    }

    public function attributes()
    {
        return $this->hasMany(ArticleAttribute::class, 'article_id', 'id');
    }

    public function getAttributesArray(int $articleID = 0)
    {
        if ($articleID) {
            $attributes = DB::table('article_attributes')
                ->where('article_id', $articleID)
                ->get();
        } else {
            $attributes = $this->attributes;
        }

        $array = [];
        foreach ($attributes as $attribute) {
            $array[$attribute->attribute] = $attribute->value;
        }

        return $array;
    }

    public function storeAttribute(string $attribute, string $value)
    {
        if ($value) {
            DB::table('article_attributes')->updateOrInsert(
                [
                    'article_id' => $this->id,
                    'attribute' => $attribute,
                ],
                [
                    'value' => $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } else {
            DB::table('article_attributes')
                ->where('article_id', $this->id)
                ->where('attribute', $attribute)
                ->delete();
        }
    }

    public function getAttribute($key)
    {
        // Get the original attribute value
        $value = parent::getAttribute($key);

        if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
            $translationServiceID = translation_service();
            if (!$translationServiceID) {
                return $value;
            }

            // Extract the language code from the attribute name
            $languageCode = $matches[1];

            // Fetch translations from the translation service
            $translation = TranslationServiceManager::getTranslation('articles', 'shop_title', $this->id, $languageCode, $translationServiceID);
            if ($translation) {
                $value = $translation->translation;
            }

            return $value;
        }
        else if (preg_match('/^shop_description_(\w+)$/', $key, $matches)) {
            $translationServiceID = translation_service();
            if (!$translationServiceID) {
                return $value;
            }

            // Extract the language code from the attribute name
            $languageCode = $matches[1];

            // Fetch translations from the translation service
            $translation = TranslationServiceManager::getTranslation('articles', 'shop_description', $this->id, $languageCode, $translationServiceID);
            if ($translation) {
                $value = $translation->translation;
            }

            return $value;
        }

        // For all other attributes, return the original value
        return $value;
    }

    public function getStockIncomingAttribute()
    {
        if (!isset($this->attributes['stock_incoming'])) {
            $this->attributes['stock_incoming'] = ArticleQuantityCalculator::getIncoming($this->article_number);
        }

        return $this->attributes['stock_incoming'];
    }

    public function getStockOnOrderAttribute()
    {
        if (!isset($this->attributes['stock_on_order'])) {
            $this->attributes['stock_on_order'] = ArticleQuantityCalculator::getOnOrder($this->article_number);
        }

        return $this->attributes['stock_on_order'];
    }

    public function getStockNetAttribute()
    {
        if (!isset($this->attributes['stock_net'])) {
            $this->attributes['stock_net'] = ArticleQuantityCalculator::getNetStock($this->article_number);
        }

        return $this->attributes['stock_net'];
    }

    public function getStockTimeAttribute()
    {
        if (!isset($this->attributes['stock_time'])) {
            $this->attributes['stock_time'] = ArticleQuantityCalculator::getStockTime($this->article_number);
        }

        return $this->attributes['stock_time'];
    }

    public function getSalesPerMonthAttribute()
    {
        if (!isset($this->attributes['sales_per_month'])) {
            $this->attributes['sales_per_month'] = ArticleQuantityCalculator::getSalesPerMonth(
                $this->article_number,
                date('Y-m-d', strtotime('-6 months')),
                date('Y-m-d')
            );
        }

        return $this->attributes['sales_per_month'];
    }

    public function getSalesPerMonth1Attribute()
    {
        if (!isset($this->attributes['sales_per_month_1'])) {
            $this->attributes['sales_per_month_1'] = ArticleQuantityCalculator::getSalesPerMonth(
                $this->article_number,
                date('Y-m-d', strtotime('-1 months')),
                date('Y-m-d')
            );
        }

        return $this->attributes['sales_per_month_1'];
    }

    public function getSalesPerMonth2Attribute()
    {
        if (!isset($this->attributes['sales_per_month_2'])) {
            $this->attributes['sales_per_month_2'] = ArticleQuantityCalculator::getSalesPerMonth(
                $this->article_number,
                date('Y-m-d', strtotime('-2 months')),
                date('Y-m-d', strtotime('-1 months')),
            );
        }

        return $this->attributes['sales_per_month_2'];
    }

    public function getSalesPerMonth3Attribute()
    {
        if (!isset($this->attributes['sales_per_month_3'])) {
            $this->attributes['sales_per_month_3'] = ArticleQuantityCalculator::getSalesPerMonth(
                $this->article_number,
                date('Y-m-d', strtotime('-3 months')),
                date('Y-m-d', strtotime('-2 months')),
            );
        }

        return $this->attributes['sales_per_month_3'];
    }

    public function getPurchasePriceAttribute()
    {
        if (!isset($this->attributes['purchase_price'])) {
            $supplierPriceService = new SupplierArticlePriceService();
            $supplierPrice = $supplierPriceService->getSupplierArticlePrice($this->article_number);

            $this->attributes['purchase_price'] = $supplierPrice->price ?? 0;
        }

        return $this->attributes['purchase_price'];
    }

    public function getPurchasePriceCurrencyAttribute()
    {
        if (!isset($this->attributes['purchase_price_currency'])) {
            $supplierPriceService = new SupplierArticlePriceService();
            $supplierPrice = $supplierPriceService->getSupplierArticlePrice($this->article_number);

            $this->attributes['purchase_price_currency'] = $supplierPrice->currency ?? 0;
        }

        return $this->attributes['purchase_price_currency'];
    }

    public function isDataComplete()
    {
        if (!$this->article_number) {
            return false;
        }
        if (!$this->description) {
            return false;
        }
        if (!$this->ean) {
            return false;
        }
        if (!$this->width || !$this->height || !$this->depth) {
            return false;
        }
        if (!$this->weight) {
            return false;
        }
        if (!$this->inner_box || !$this->master_box) {
            return false;
        }
        if (!$this->package_image_front || !$this->package_image_back) {
            return false;
        }

        return true;
    }

    public function getMainImage(): string
    {
        $articleImage = ArticleImage::where('article_id', $this->id)
            ->orderBy('list_order', 'ASC')
            ->first();

        return ($articleImage->path_url ?? '');
    }

    public function linkedChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'related_articles',
            'parent_article_id',
            'child_article_id'
        )->withTimestamps();
    }

    public function linkedParents(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'related_articles',
            'child_article_id',
            'parent_article_id'
        )->withTimestamps();
    }

    public function getLinkedArticlesAttribute()
    {
        return $this->linkedChildren->merge($this->linkedParents)->unique('id')->values();
    }

    public function scopeWithLinked($query)
    {
        return $query->with(['linkedChildren', 'linkedParents']);
    }

    public static function getOutletPrices(int $articleID): array
    {
        $article = Article::find($articleID);

        $ecbService = new EcbService();

        $baseCurrency = 'SEK';

        $priceMode = $article->outlet_price_mode ?: 'Relative';

        if ($priceMode == 'Relative') {
            $discount = $article->outlet_discount / 100;
            $maxDiscount = $article->outlet_max_discount / 100;
            $innerWeight = $article->outlet_inner_weight / 100;

            $innerDiscount = $discount + ($maxDiscount * $innerWeight);
            $masterDiscount = $discount + $maxDiscount;

            $unitPrice = round($article->{'rek_price_' . $baseCurrency} * (1 - $discount));
            $unitPriceInner = round($article->{'rek_price_' . $baseCurrency} * (1 - $innerDiscount));
            $unitPriceMaster = round($article->{'rek_price_' . $baseCurrency} * (1 - $masterDiscount));
        }
        elseif ($priceMode == 'Relative price') {
            $innerWeight = $article->outlet_inner_weight / 100;

            $unitPrice = (int) $article->outlet_price;
            $unitPriceMaster = (int) $article->outlet_max_price;
            $unitPriceInner = round($unitPrice - (($unitPrice - $unitPriceMaster) * $innerWeight));
        }
        elseif ($priceMode == 'Fixed price') {
            $unitPrice = (int) $article->outlet_price_fixed;
            $unitPriceInner = (int) $article->outlet_inner_price_fixed;
            $unitPriceMaster = (int) $article->outlet_master_price_fixed;
        }
        else {
            $unitPrice = 0;
            $unitPriceInner = 0;
            $unitPriceMaster = 0;
        }


        $prices = [
            'unit' => [
                $baseCurrency => $unitPrice
            ],
            'inner' => [
                $baseCurrency => $unitPriceInner
            ],
            'master' => [
                $baseCurrency => $unitPriceMaster
            ]
        ];

        foreach (CurrencyController::SUPPORTED_CURRENCIES as $currency) {
            if ($currency == $baseCurrency) continue;

            $roundPrecision = ($currency == 'EUR') ? 1 : 0;

            $prices['unit'][$currency] = round($ecbService->convertCurrency($prices['unit'][$baseCurrency], $baseCurrency, $currency), $roundPrecision);
            $prices['inner'][$currency] = round($ecbService->convertCurrency($prices['inner'][$baseCurrency], $baseCurrency, $currency), $roundPrecision);
            $prices['master'][$currency] = round($ecbService->convertCurrency($prices['master'][$baseCurrency], $baseCurrency, $currency), $roundPrecision);
        }

        return $prices;
    }
}
