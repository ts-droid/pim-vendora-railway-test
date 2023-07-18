<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_receipt_id',
        'line_key',
        'article_number',
        'description',
        'unit_cost',
        'quantity',
        'total_cost'
    ];
}
