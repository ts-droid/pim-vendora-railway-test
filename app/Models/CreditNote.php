<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_number',
        'date',
        'status',
        'customer_number',
        'currency',
        'amount',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_number', 'customer_number');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class, 'credit_note_id', 'id')->with('article');
    }
}
