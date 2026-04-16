<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_article_number',
        'component_article_number',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'bundle_article_number', 'article_number');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'component_article_number', 'article_number');
    }
}
