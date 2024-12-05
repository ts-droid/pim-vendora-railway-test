<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlaceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stock_place',
        'stock_place_compartments'
    ];

    protected $casts = [
        'stock_place' => 'array',
        'stock_place_compartments' => 'array'
    ];
}
