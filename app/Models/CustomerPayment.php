<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'status',
        'customer_number',
        'application_date',
        'payment_reference',
        'currency',
        'payment_amount',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerPaymentLine::class, 'customer_payment_id', 'id');
    }
}
