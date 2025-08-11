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
    ];

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_shipment_id', 'id');
    }
}
