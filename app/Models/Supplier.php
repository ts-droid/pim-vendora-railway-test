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
        'purchase_system',
        'purchase_master_box',
        'purchase_inner_box',
        'purchase_ai',
        'purchase_order_interval',
        'email',
        'email_reminder',
    ];
}
