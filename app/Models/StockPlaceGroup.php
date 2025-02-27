<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlaceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_places',
        'max_volume',
        'min_volume',
    ];

    protected $casts = [
        'stock_places' => 'array'
    ];
}
