<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderException extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_shipment_id',
        'purchase_order_line_id',
        'diff',
        'exception_type',
        'images',
    ];

    protected $casts = [
        'images' => 'array'
    ];
}
