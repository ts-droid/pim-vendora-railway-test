<?php

namespace App\Models;

use App\Enums\ShipmentInternalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'operation',
        'status',
        'internal_status',
        'on_hold',
        'date',
        'customer_number',
        'delivery_address_id',
        'name',
        'attention',
        'email',
        'phone',
        'order_numbers',
        'ping_at',
        'tracking_number',
        'completed_at',
        'pick_signature',
        'pack_signature',
        'note'
    ];

    protected $casts = [
        'order_numbers' => 'array',
        'internal_status' => ShipmentInternalStatus::class,
    ];

    public function address()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function lines()
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function salesOrder()
    {
        return SalesOrder::whereIn('order_number', $this->order_numbers ?: [])->first();
    }

    public function isBackorder()
    {
        return Cache::remember('shipment:' . $this->id . ':is_backorder', (6 * 3600), function() {
            $orderNumbers = $this->order_numbers;

            return (bool) Shipment::where('number', '<', $this->number)
                ->where(function($query) use ($orderNumbers) {
                    foreach ($orderNumbers as $orderNumber) {
                        $query->orWhere('order_numbers', 'LIKE', '%"' . $orderNumber . '"%');
                    }
                })
                ->exists();
        });
    }

    public function calculateTotalQuantity()
    {
        return $this->lines->sum('picked_quantity');
    }

    public function calculateTotalWeight()
    {
        $totalWeight = 0;

        foreach ($this->lines as $line) {
            $qty = $line->picked_quantity;
            $weight = $line->article->weight ?? 0;

            $totalWeight += ($qty * $weight);
        }

        return $totalWeight;
    }

    public function getBrandingData(): array
    {
        $defaultBranding = [
            'brand_name' => 'Vendora Nordic AB',
            'logo_url' => asset('/assets/img/logos/logo_vendora.png'),
            'logo_path' => public_path('/assets/img/logos/logo_vendora.png'),
            'logo_multiplier' => 1,
            'language_code' => 'en'
        ];

        $orderNumber = $this->order_numbers[0] ?? null;
        if (!$orderNumber) {
            return $defaultBranding;
        }

        $salesOrder = SalesOrder::where('order_number', $orderNumber)->first();
        if (!$salesOrder) {
            return $defaultBranding;
        }

        return $salesOrder->getBrandingDate();
    }
}
