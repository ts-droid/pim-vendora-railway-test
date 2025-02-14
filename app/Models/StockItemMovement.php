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
        'ping_at',
        'is_investigation',
        'type' // refill, organization, unleash
    ];

    public function fromStockPlaceCompartment()
    {
        return $this->belongsTo(StockPlaceCompartment::class, 'from_stock_place_compartment');
    }

    public function toStockPlaceCompartment()
    {
        return $this->belongsTo(StockPlaceCompartment::class, 'to_stock_place_compartment');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number')
            ->select(['description', '']);
    }
}
