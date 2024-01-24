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
        'percent',
        'percent_'
    ];
}
