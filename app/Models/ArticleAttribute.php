<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'attribute',
        'value',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
