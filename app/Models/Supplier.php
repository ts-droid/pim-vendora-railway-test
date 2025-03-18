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
        'type',
        'brand_name',
        'class',
        'class_description',
        'credit_terms',
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
        'access_key',
        'main_address_line',
        'main_address_city',
        'main_address_country',
        'remit_address_line',
        'remit_address_city',
        'remit_address_country',
        'supplier_address_line',
        'supplier_address_city',
        'supplier_address_country',
        'main_contact_name',
        'main_contact_attention',
        'main_contact_email',
        'main_contact_phone1',
        'main_contact_phone2',
        'remit_contact_name',
        'remit_contact_attention',
        'remit_contact_email',
        'remit_contact_phone1',
        'remit_contact_phone2',
        'supplier_contact_name',
        'supplier_contact_attention',
        'supplier_contact_email',
        'supplier_contact_phone1',
        'supplier_contact_phone2',
    ];
}
