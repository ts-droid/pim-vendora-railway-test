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
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function lines()
    {
        return $this->hasMany(ShipmentLine::class);
    }
}
