<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BID-variant — en prisvariant på en artikel.
 *
 * Ärver all grunddata (namn, EAN, varumärke, leverantör, kostnad) från
 * parent-artikeln via relationen article(). Endast variant-SKU,
 * variant-cost, fast pris, min-marginal och sort-ordning lever här.
 */
class BidVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'variant_sku',
        'cost',
        'fixed_price',
        'min_margin',
        'sort_order',
    ];

    protected $casts = [
        'cost' => 'float',
        'fixed_price' => 'float',
        'min_margin' => 'float',
        'sort_order' => 'int',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
