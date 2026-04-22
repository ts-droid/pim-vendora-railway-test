<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whitelist-rad: customer {customer_number} får se BID för article
 * {article_number}.
 *
 * En rad = full tillgång till alla bid_variants på artikeln. Utan
 * rad = kunden ser inga BID-varianter för den artikeln oavsett
 * articles.bid_enabled.
 */
class CustomerBidAccess extends Model
{
    use HasFactory;

    protected $table = 'customer_bid_access';

    protected $fillable = [
        'customer_number',
        'article_number',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_number', 'customer_number');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
