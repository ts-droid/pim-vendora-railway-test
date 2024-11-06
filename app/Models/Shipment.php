<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'status',
        'on_hold',
        'date',
        'customer_number',
        'delivery_address_id',
        'name',
        'attention',
        'email',
        'phone',
        'order_numbers',
    ];

    protected $casts = [
        'order_numbers' => 'array',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function lines()
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function isBackorder()
    {
        $orderNumbers = $this->order_numbers;

        return (bool) Shipment::where('number', '<', $this->number)
            ->where(function($query) use ($orderNumbers) {
                foreach ($orderNumbers as $orderNumber) {
                    $query->orWhere('order_numbers', 'LIKE', '%"' . $orderNumber . '"%');
                }
            })
            ->exists();
    }
}
