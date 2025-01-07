<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlaceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_places'
    ];

    protected $casts = [
        'stock_places' => 'array'
    ];

    public function stockPlaces()
    {
        return $this->hasMany(StockPlace::class, 'id', 'stock_places');
    }
}
