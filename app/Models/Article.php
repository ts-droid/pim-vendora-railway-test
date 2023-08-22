<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'article_number',
        'description',
        'ean',
        'wright_article_number',
        'supplier_number',
        'cost_price_avg',
        'external_cost',
        'rek_price_SEK',
        'rek_price_EUR',
        'rek_price_DKK',
        'rek_price_NOK',
        'shop_title_sv',
        'shop_title_en',
        'shop_title_da',
        'shop_description_sv',
        'shop_description_en',
        'shop_description_da',
        'video',
        'stock',
        'hs_code',
        'origin_country',
        'inner_box',
        'master_box',
        'width',
        'height',
        'depth',
        'master_box_width',
        'master_box_height',
        'master_box_depth',
        'inner_box_width',
        'inner_box_height',
        'inner_box_depth',
        'weight',
        'master_box_weight',
        'inner_box_weight',
        'brand',
        'is_webshop',
        'sales_30_days',
        'webshop_created_at',
        'review_links',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_number', 'number');
    }

    public function stock_logs()
    {
        return $this->hasMany(StockLog::class, 'article_number', 'article_number');
    }
}
