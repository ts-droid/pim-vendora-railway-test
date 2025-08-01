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
        'limit',
        'manufacturer_information',
        'eu_representative',
        'general_delivery_time',
        'purchase_min_value',
        'purchase_min_quantity',
        'shipping_instructions',

        'main_contact_name',
        'main_contact_attention',
        'main_contact_email',
        'main_contact_phone1',
        'main_contact_phone2',
        'main_address_line',
        'main_address_city',
        'main_address_country',

        'remit_contact_name',
        'remit_contact_attention',
        'remit_contact_email',
        'remit_contact_phone1',
        'remit_contact_phone2',
        'remit_address_line',
        'remit_address_city',
        'remit_address_country',

        'supplier_contact_name',
        'supplier_contact_attention',
        'supplier_contact_email',
        'supplier_contact_phone1',
        'supplier_contact_phone2',
        'supplier_address_line',
        'supplier_address_city',
        'supplier_address_country',

        'po_contact_name',
        'po_contact_attention',
        'po_contact_email',
        'po_contact_phone1',
        'po_contact_phone2',
        'po_address_line',
        'po_address_city',
        'po_address_country'
    ];

    protected $casts = [
        'external_id' => 'string',
        'number' => 'string',
        'vat_number' => 'string',
        'org_number' => 'string',
        'name' => 'string',
        'type' => 'string',
        'brand_name' => 'string',
        'class' => 'string',
        'class_description' => 'string',
        'credit_terms' => 'string',
        'credit_terms_description' => 'string',
        'currency' => 'string',
        'language' => 'string',
        'email' => 'string',
        'email_reminder' => 'string',
        'access_key' => 'string',
        'manufacturer_information' => 'string',
        'eu_representative' => 'string',
        'shipping_instructions' => 'string',

        'main_contact_name' => 'string',
        'main_contact_attention' => 'string',
        'main_contact_email' => 'string',
        'main_contact_phone1' => 'string',
        'main_contact_phone2' => 'string',
        'main_address_line' => 'string',
        'main_address_city' => 'string',
        'main_address_country' => 'string',

        'remit_contact_name' => 'string',
        'remit_contact_attention' => 'string',
        'remit_contact_email' => 'string',
        'remit_contact_phone1' => 'string',
        'remit_contact_phone2' => 'string',
        'remit_address_line' => 'string',
        'remit_address_city' => 'string',
        'remit_address_country' => 'string',

        'supplier_contact_name' => 'string',
        'supplier_contact_attention' => 'string',
        'supplier_contact_email' => 'string',
        'supplier_contact_phone1' => 'string',
        'supplier_contact_phone2' => 'string',
        'supplier_address_line' => 'string',
        'supplier_address_city' => 'string',
        'supplier_address_country' => 'string',

        'po_contact_name' => 'string',
        'po_contact_attention' => 'string',
        'po_contact_email' => 'string',
        'po_contact_phone1' => 'string',
        'po_contact_phone2' => 'string',
        'po_address_line' => 'string',
        'po_address_city' => 'string',
        'po_address_country' => 'string'
    ];

    public function contacts()
    {
        return $this->hasMany(SupplierContact::class, 'supplier_id', 'id');
    }
}
