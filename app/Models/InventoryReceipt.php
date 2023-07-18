<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'date',
        'status',
        'total_cost',
        'total_quantity',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryReceiptLine::class, 'inventory_receipt_id', 'id');
    }
}
