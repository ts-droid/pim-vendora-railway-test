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
        'article_number',
        'diff',
        'exception_type',
        'images',
        'handled_at',
    ];

    protected $casts = [
        'images' => 'array'
    ];

    public function purchaseOrderShipment()
    {
        return $this->belongsTo(PurchaseOrderShipment::class);
    }

    public function line()
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id', 'id');
    }
}
