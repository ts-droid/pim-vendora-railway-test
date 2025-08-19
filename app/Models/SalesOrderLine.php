<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'vat_rate',
        'is_direct'
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }
}
