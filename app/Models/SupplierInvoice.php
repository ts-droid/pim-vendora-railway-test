<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'filename',
        'client_filename'
    ];

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'invoice_id', 'id');
    }
}
