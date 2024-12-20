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
        'from_compartment_section',
        'to_stock_place_compartment',
        'to_compartment_section',
        'quantity',
        'ping_at',
        'is_investigation',
        'type'
    ];

    public function fromStockPlaceCompartment()
    {
        return $this->belongsTo(StockPlaceCompartment::class, 'from_stock_place_compartment');
    }

    public function toStockPlaceCompartment()
    {
        return $this->belongsTo(StockPlaceCompartment::class, 'to_stock_place_compartment');
    }

    public function fromCompartmentSection()
    {
        return $this->belongsTo(CompartmentSection::class, 'from_compartment_section');
    }

    public function toCompartmentSection()
    {
        return $this->belongsTo(CompartmentSection::class, 'to_compartment_section');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number')
            ->select(['description', '']);
    }
}
