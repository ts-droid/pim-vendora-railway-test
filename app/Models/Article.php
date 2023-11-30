<?php

namespace App\Models;

use App\Services\ArticleQuantityCalculator;
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
        'sales_per_month',
    ];

    protected static function booted()
    {
        static::retrieved(function ($article) {
            $article->stock_incoming = ArticleQuantityCalculator::getIncoming($article->article_number);
            $article->stock_on_order = ArticleQuantityCalculator::getOnOrder($article->article_number);
            $article->stock_net = ArticleQuantityCalculator::getNetStock($article->article_number);
            $article->stock_time = ArticleQuantityCalculator::getStockTime($article->article_number);
            $article->sales_per_month = ArticleQuantityCalculator::getSalesPerMonth($article->article_number);
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
            $this->attributes['sales_per_month'] = ArticleQuantityCalculator::getSalesPerMonth($this->article_number);
        }

        return $this->attributes['sales_per_month'];
    }
}
