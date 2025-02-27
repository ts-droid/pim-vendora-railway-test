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
        'volume_class',
        'width',
        'height',
        'depth',
        'is_truck',
        'is_movable',
        'is_walk_through',
        'is_manual',
        'template_id',
        'template_group',
        'unleash',
        'list_order',
    ];

    public function stockPlace()
    {
        return $this->belongsTo(StockPlace::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }

    public function sections()
    {
        return $this->hasMany(CompartmentSection::class)->orderBy('id', 'ASC');
    }

    public function is_reserved()
    {
        return StockPlaceCompartmentReservation::where('stock_place_compartment_id', $this->id)
            ->where('reserved_until', '>', date('Y-m-d H:i:s'))
            ->exists();
    }
}
