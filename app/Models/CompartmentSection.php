<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompartmentSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_place_compartment_id'
    ];

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }
}
