<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticlePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'customer_id',
        'base_price_SEK',
        'base_price_EUR',
        'base_price_DKK',
        'base_price_NOK',
        'percent',
        'percent_inner',
        'percent_master',
    ];
}
