<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnspscCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'commodity',
        'commodity_title',
    ];
}
