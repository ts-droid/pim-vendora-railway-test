<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'line_number',
        'order_number',
        'article_number',
        'description',
        'quantity',
        'shipped_quantity',
    ];
}
