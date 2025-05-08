<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'first_name',
        'last_name',
        'attention',
        'street_line_1',
        'street_line_2',
        'postal_code',
        'city',
        'country_code',
    ];
}
