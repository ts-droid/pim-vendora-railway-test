<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'date',
        'due_date',
        'status',
        'customer_number',
        'credit_terms',
        'currency',
        'amount',
        'paid_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_number', 'customer_number');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerInvoiceLine::class, 'customer_invoice_id', 'id')->with('article', 'sales_person');
    }
}
