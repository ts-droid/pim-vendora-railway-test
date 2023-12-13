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
        'suggested_quantity',
        'suggested_quantity_month',
        'unit_cost',
        'amount',
        'promised_date',
        'ai_comment',
        'user_comment',
        'is_vip',
        'is_completed',
        'is_canceled',
        'is_locked',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
