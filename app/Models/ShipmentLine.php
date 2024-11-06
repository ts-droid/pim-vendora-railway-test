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
        $salesOrderID = (int) SalesOrder::select('id')
            ->where('order_number', '=', $this->order_number)
            ->pluck('id')
            ->first();

        return (int) SalesOrderLine::select('quantity')
            ->where('sales_order_id', '=', $salesOrderID)
            ->where('line_number', '=', $this->order_line_number)
            ->pluck('quantity')
            ->first();
    }
}
