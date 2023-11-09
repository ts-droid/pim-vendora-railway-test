<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'customer_number',
        'vat_number',
        'org_number',
        'name',
        'country',
        'shop_url',
        'shop_search_url',
        'logo_path',
        'logo_url',
        'sales_person_id',
        'sales_last_30_days',
        'is_hidden',
        'disable_auto_order_process',
    ];
}
