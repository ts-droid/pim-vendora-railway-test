<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItemMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'from_stock_place_compartment',
        'to_stock_place_compartment',
        'quantity',
    ];
}
