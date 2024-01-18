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
        'regenerate_only_existing',
        'is_generating',
        'reminder_sent_at',
        'should_delete',
        'user_deleted_at',
        'draft_num_reminders_sent',
        'draft_reminder_sent_at',
        'published_at',
        'is_sent',
        'is_confirmed',
        'is_po_system',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_number', 'number');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id', 'id')
            ->orderBy('is_locked', 'DESC')
            ->orderBy('line_key', 'ASC')
            ->orderBy('id', 'ASC');
    }

    public function getHash(): string
    {
        return hash('md5', ($this->id . $this->created_at));
    }
}
