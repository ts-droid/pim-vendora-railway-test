<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleMarketingContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_sv',
        'title_en',
        'title_da',
        'system',
        'message',
    ];
}
