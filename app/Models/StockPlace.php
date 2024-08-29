<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'map_position_x',
        'map_position_y',
        'map_size_x',
        'map_size_y',
        'color',
        'type',
    ];

    public function compartments()
    {
        return $this->hasMany(StockPlaceCompartment::class);
    }

    public function is_walk_through()
    {
        if (!$this->compartments->count()) {
            return false;
        }

        $lastCompartment = $this->compartments->last();

        return (bool) $lastCompartment->is_walk_through;
    }
}
