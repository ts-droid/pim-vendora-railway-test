<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marginalregel — ÅF-marginal + min. vår marginal per (varumärke × kategori).
 *
 * brand=NULL   → alla varumärken
 * category_id=NULL → alla kategorier
 * Båda NULL    → global standard
 *
 * MarginResolver väljer den mest specifika regeln som matchar en artikel.
 */
class MarginRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'category_id',
        'reseller_margin',
        'minimum_margin',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'reseller_margin' => 'float',
        'minimum_margin' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    public function isGlobalDefault(): bool
    {
        return $this->brand === null && $this->category_id === null;
    }
}
