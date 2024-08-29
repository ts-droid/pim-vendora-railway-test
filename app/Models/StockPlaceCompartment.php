<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlaceCompartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'stock_place_id',
        'width',
        'height',
        'depth',
        'is_truck',
        'is_movable',
        'is_walk_through',
    ];

    public function stockPlace()
    {
        return $this->belongsTo(StockPlace::class);
    }
}
