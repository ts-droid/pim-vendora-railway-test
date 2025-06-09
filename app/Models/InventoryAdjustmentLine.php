<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_adjustment_id',
        'line_number',
        'article_number',
        'quantity',
        'unit_cost',
        'ext_cost',
        'reason_code',
        'description'
    ];
}
