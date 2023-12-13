<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'status',
        'date',
        'promised_date',
        'supplier_id',
        'supplier_number',
        'supplier_name',
        'currency',
        'amount',
        'is_draft',
        'is_vip',
        'foresight_days',
        'email',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_number', 'number');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id', 'id');
    }

    public function getHash(): string
    {
        return hash('md5', ($this->id . $this->created_at));
    }
}
