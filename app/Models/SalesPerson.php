<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesPerson extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'show_sales_dashboard',
        'is_operating_cost',
        'basal_compensation',
        'commission',
        'sample_amount'
    ];
}
