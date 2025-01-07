<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPlaceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_places',
        'max_volume_class_size_a',
        'max_volume_class_size_b',
        'max_volume_class_size_c',
        'wms_multi_intelligence',
        'wms_multi_intelligence_period',
    ];

    protected $casts = [
        'stock_places' => 'array'
    ];
}
