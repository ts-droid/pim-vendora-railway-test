<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanceledPurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'article_number',
        'description',
        'unit_price',
        'quantity',
    ];
}
