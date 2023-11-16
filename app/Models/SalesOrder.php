<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_type',
        'order_number',
        'status',
        'invoice_number',
        'sales_person',
        'date',
        'customer',
        'currency',
        'order_total',
        'order_total_quantity',
        'exchange_rate',
        'note',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'purchase_order_id', 'id');
    }
}
