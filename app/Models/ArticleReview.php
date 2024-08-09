<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'name',
        'content',
        'ip',
        'stars',
        'default_language',
        'published_at',
    ];
}
