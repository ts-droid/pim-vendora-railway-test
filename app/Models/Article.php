<?php

namespace App\Models;

use App\Services\ArticleQuantityCalculator;
use App\Services\SupplierArticlePriceService;
use App\Services\TranslationServiceManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'category_ids' => 'array',
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
        });

        static::updated(function ($article) {
            $changes = $article->getChanges();

            event(new \App\Events\ArticleUpdated($article, $changes));
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
        else if (preg_match('/^shop_title_(\w+)$/', $key, $matches)) {
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
}
