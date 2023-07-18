<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerInvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_invoice_id',
        'line_key',
        'article_number',
        'description',
        'order_number',
        'shipment_number',
        'line_type',
        'quantity',
        'unit_price',
        'amount',
        'cost',
        'sales_person_id'
    ];
}
