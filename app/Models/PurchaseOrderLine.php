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
        'unit_cost',
        'amount',
        'promised_date',
        'ai_comment',
        'user_comment',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
