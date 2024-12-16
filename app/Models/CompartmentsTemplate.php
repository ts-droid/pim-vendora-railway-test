<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompartmentsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    const TEMPLATE_COLUMNS = [
        'volume_class',
        'width',
        'height',
        'depth',
        'is_truck',
        'is_movable',
        'is_walk_through',
        'is_manual',
    ];
}
