<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_payment_id',
        'document_type',
        'reference_number',
        'amount_paid',
        'date',
        'due_date',
        'balance',
        'currency',
    ];
}
