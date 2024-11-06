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
        'order_line_number',
        'article_number',
        'description',
        'quantity',
        'shipped_quantity',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }

    public function orderQuantity()
    {

    }
}
