<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_number',
        'stock_place_compartment_id',
        'quantity',
        'signature',
        'source'
    ];

    public function stockPlaceCompartment()
    {
        return $this->belongsTo(StockPlaceCompartment::class);
    }
}
