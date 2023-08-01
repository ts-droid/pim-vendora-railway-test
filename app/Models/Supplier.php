<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'number',
        'vat_number',
        'org_number',
        'name',
        'brand_name',
        'class_description',
        'credit_terms_description',
        'currency',
        'language',
        'is_supplier',
    ];
}
