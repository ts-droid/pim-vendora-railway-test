<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artikelspecifikt stöd eller kampanj (rebate/upfront/funding).
 *
 * En artikel kan ha flera stöd-rader — t.ex. leverantörs-upfront
 * under en kvartal och ett varumärkes-rebate över hela året.
 */
class ArticleSupport extends Model
{
    use HasFactory;

    // Riktning på stödet:
    //   SUPPLIER = inkommande pengar/värde från leverantör till oss på varan
    //   CUSTOMER = utgående rabatt/stöd som visas mot kund i prislistor
    public const LAYER_SUPPLIER = 'supplier';
    public const LAYER_CUSTOMER = 'customer';

    public const CUSTOMER_TYPE_UPFRONT = 'upfront';
    public const CUSTOMER_TYPE_REBATE = 'rebate';
    public const CUSTOMER_TYPE_OTHER = 'other';

    protected $fillable = [
        'article_number',
        'layer',
        'customer_type',
        'value',
        'is_percentage',
        'currency',
        'date_from',
        'date_to',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'float',
        'is_percentage' => 'boolean',
        'date_from' => 'date',
        'date_to' => 'date',
        'sort_order' => 'int',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
