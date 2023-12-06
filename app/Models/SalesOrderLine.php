<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'line_number',
        'article_number',
        'invoice_number',
        'sales_person',
        'quantity',
        'quantity_on_shipments',
        'quantity_open',
        'unit_cost',
        'unit_price',
        'description',
        'is_completed',
    ];
}
