<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'line_key',
        'article_number',
        'description',
        'quantity',
        'quantity_on_shipments',
        'quantity_open',
        'suggested_quantity',
        'unit_cost',
        'amount',
        'promised_date',
        'ai_comment',
        'user_comment',
        'is_vip',
        'is_completed',
        'is_canceled',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
