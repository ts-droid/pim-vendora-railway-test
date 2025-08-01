<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'attention',
        'email',
        'phone1',
        'phone2',
        'address_line',
        'address_city',
        'address_country'
    ];
}
