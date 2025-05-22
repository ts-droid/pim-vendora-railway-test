<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleFaqEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'question',
        'answer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
