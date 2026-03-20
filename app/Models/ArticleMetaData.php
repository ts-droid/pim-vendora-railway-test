<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleMetaData extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'type',
        'value'
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
