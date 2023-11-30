<?php

namespace App\Models;

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

    protected static function booted()
    {
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
}
