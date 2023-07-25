<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'from_currency',
        'to_currency',
        'type',
        'rate',
        'date',
        'mult_div',
        'rate_reciprocal',
    ];
}
