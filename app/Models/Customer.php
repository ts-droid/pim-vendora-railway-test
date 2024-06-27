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
        'allianz_company_id',
        'vat_number',
        'org_number',
        'name',
        'country',
        'alternate_countries',
        'shop_url',
        'shop_search_url',
        'logo_path',
        'logo_url',
        'sales_person_id',
        'sales_last_30_days',
        'is_hidden',
        'disable_auto_order_process',
        'credit_limit',
        'credit_terms',
        'credit_balance',
        'vendora_rating',
        'access_key',
        'revenue',
        'suggestions_articles',
        'intel_articles',
        'average_payment_days',
        'worst_payment_days',
        'worst_payment_invoice_id',
        'return_rate',
    ];
}
