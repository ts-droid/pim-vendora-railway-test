<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    const PORTAL_STATUS_UNCONFIRMED = 'unconfirmed';
    const PORTAL_STATUS_OPEN = 'open';
    const PORTAL_STATUS_CLOSED = 'closed';

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
        'viewed_at',
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

    public function hasInvoice(): bool
    {
        return $this->lines->where('invoice_id', '!=', 0)->count() > 0;
    }

    public function isFullyInvoiced(): bool
    {
        return $this->lines->where('invoice_id', 0)->count() === 0;
    }

    public function missingTrackingNumbers(): bool
    {
        return $this->lines->where('tracking_number', '')
                ->orWhereNull('tracking_number')
                ->count() > 0;
    }

    public function getPortalStatus(): string
    {
        if (!$this->published_at) {
            return self::PORTAL_STATUS_UNCONFIRMED;
        }

        foreach ($this->lines as $line) {
            if ($line->is_completed == 0) {
                return self::PORTAL_STATUS_OPEN;
            }
        }

        return self::PORTAL_STATUS_CLOSED;
    }

    public function getColorStatus()
    {
        $hasData = false;
        $missingData = false;

        foreach ($this->lines as $line) {
            if ($line->promised_date || $line->tracking_number) {
                $hasData = true;
            }

            if (!$line->promised_date || !$line->tracking_number) {
                $missingData = true;
            }
        }

        if (!$this->isFullyInvoiced()) {
            $missingData = true;
        }

        if ($hasData) {
            if ($missingData) {
                // Some data is missing for the order
                return ['orange', 'Some information is missing'];
            }
            else {
                // No data is missing for the order
                return ['green', 'All data is complete'];
            }
        }
        else {
            // No data is present for the order
            return ['red', 'All information is missing'];
        }
    }
}
