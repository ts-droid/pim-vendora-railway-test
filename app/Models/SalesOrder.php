<?php

namespace App\Models;

use App\Services\BrandPageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_type',
        'order_number',
        'customer_ref_no',
        'status',
        'invoice_number',
        'sales_person',
        'date',
        'customer',
        'currency',
        'language',
        'order_total',
        'order_total_quantity',
        'exchange_rate',
        'note',
        'internal_note',
        'store_note',
        'on_hold',
        'source',
        'shipping_address_id',
        'billing_address_id',
        'phone',
        'email',
        'billing_email',
        'pay_method',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer', 'customer_number');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id', 'id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SalesOrderLog::class, 'sales_order_id', 'id');
    }

    public function getBrandingDate(): array
    {
        if ($this->source) {
            $brandPageService = new BrandPageService();
            $response = $brandPageService->callAPI('GET', '/v1/pages/site/get-by-domain', [
                'domain' => $this->source
            ]);

            if ($response['success']) {
                $domain = $response['data']['domain'] ?? '';
                $brandName = $response['data']['name'] ?? '';
                $logoPath = $response['data']['logo']['path'] ?? '';

                if ($domain && $brandName && $logoPath) {
                    return [
                        'brand_name' => $brandName,
                        'logo_url' => 'https://' . $domain . '/storage/' . $logoPath,
                        'logo_path' => null,
                        'language_code' => $this->language,
                    ];
                }
            }
        }

        return [
            'brand_name' => 'Vendora Nordic AB',
            'logo_url' => asset('/assets/img/logos/logo_vendora.png'),
            'logo_path' => public_path('/assets/img/logos/logo_vendora.png'),
            'language_code' => $this->language,
        ];
    }
}
