<?php

namespace App\Models;

use App\Services\BrandPageService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $appends = ['order_total_incl_vat'];

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
        'store_pay_method',
        'vat_number',
        'is_company',
        'has_sync_error',
        'status_sent_external',
        'status_shipment_created',
        'status_shipment_picked',
        'status_shipment_sent',
        'payment_reference'
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer', 'external_id');
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

    public function orderTotalInclVat(): Attribute
    {
        return Attribute::get(function () {
            $this->loadMissing('lines');

            return $this->lines->sum(fn ($line)  =>
                $line->unit_price * $line->quantity * (1 + ($line->vat_rate / 100))
            );
        });
    }

    public function getOrderTotalWithVat(): float
    {
        $total = 0;

        if ($this->lines) {
            foreach ($this->lines as $salesOrderLine) {
                $total += add_vat($salesOrderLine->unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate);
            }
        }

        return $total;
    }

    public function orderHasShipping()
    {
        if ($this->lines) {
            foreach ($this->lines as $salesOrderLine) {
                if ($salesOrderLine->article_number === 'SHIP25') {
                    return true;
                }
            }
        }

        return false;
    }

    public function isBrandPageOrder(): bool
    {
        if (!$this->source || $this->source == 'visma_net') {
            return false;
        }

        $endpoint = 'https://' . $this->source . '/api/v1/pages/site/get-by-domain';

        $brandPageService = new BrandPageService();
        $response = $brandPageService->callAPI('GET', $endpoint, [
            'domain' => $this->source
        ]);

        if (!$response['success']) {
            return false;
        }

        $domain = $response['data']['domain'] ?? '';
        if (!$domain) {
            return false;
        }

        return true;
    }

    public function getBrandingDate(): array
    {
        if ($this->source && $this->source != 'visma_net') {
            $endpoint = 'https://' . $this->source . '/api/v1/pages/site/get-by-domain';

            $brandPageService = new BrandPageService();
            $response = $brandPageService->callAPI('GET', $endpoint, [
                'domain' => $this->source
            ]);

            if ($response['success']) {
                $domain = $response['data']['domain'] ?? '';
                $brandName = $response['data']['name'] ?? '';
                $logoPath = $response['data']['logo']['path'] ?? '';
                $logoMultiplier = $response['data']['logo_multiplier'] ?? 1;

                if ($domain && $brandName && $logoPath) {
                    return [
                        'is_brand' => true,
                        'brand_name' => $brandName,
                        'logo_url' => 'https://' . $domain . '/storage/' . $logoPath,
                        'logo_path' => null,
                        'logo_multiplier' => $logoMultiplier,
                        'customer_review_url' => 'https://' . $domain . '/{lang}/customer-review?sku={sku}&rating={rating}',
                        'language_code' => $this->language,
                    ];
                }
            }
        }

        return [
            'is_brand' => false,
            'brand_name' => 'Vendora Nordic AB',
            'logo_url' => asset('/assets/img/logos/logo_vendora.png'),
            'logo_path' => public_path('/assets/img/logos/logo_vendora.png'),
            'logo_multiplier' => 1,
            'customer_review_url' => route('customer.review', ['article_id' => '{article_id}', 'lang' => '{lang}', 'rating' => '{rating}']),
            'language_code' => $this->language,
        ];
    }
}
