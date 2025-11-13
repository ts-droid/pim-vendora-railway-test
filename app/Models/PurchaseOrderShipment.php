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
        'tracking_number',
        'comment',
        'is_completed',
        'completed_at',
        'completed_by'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function lines()
    {
        return $this->belongsToMany(
            PurchaseOrderLine::class,
            'purchase_order_shipment_lines',
            'purchase_order_shipment_id',
            'purchase_order_line_id'
        );
    }
}
