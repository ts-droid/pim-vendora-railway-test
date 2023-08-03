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

    public function customerInvoice()
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number')->with('supplier');
    }

    public function sales_person()
    {
        return $this->belongsTo(SalesPerson::class, 'sales_person_id', 'external_id');
    }
}
