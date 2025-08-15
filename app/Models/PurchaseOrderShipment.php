<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'receipt',
        'tracking_number'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_shipment_id', 'id');
    }
}
