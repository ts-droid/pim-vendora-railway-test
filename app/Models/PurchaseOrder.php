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
        'currency_rate',
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
        'supplier_order_number',
        'shipping_instructions',
        'is_direct',
        'direct_order',

        'status_sent_to_supplier',
        'status_sent_external',
        'status_confirmed_by_supplier',
        'status_shipping_details',
        'status_tracking_number',
        'status_invoice_uploaded',
        'status_received',

        'confirm_reminder_sent_at',
        'shipping_reminder_sent_at',
        'invoice_reminder_sent_at',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_number', 'number');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id', 'id')
            ->orderBy('id', 'ASC');
    }

    public function canceledLines(): HasMany
    {
        return $this->hasMany(CanceledPurchaseOrderLine::class, 'purchase_order_id', 'id')
            ->orderBy('id', 'ASC');
    }

    public function directOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'direct_order', 'id');
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
        return $this->lines->filter(function ($line) {
            return $line->tracking_number === null || $line->tracking_number === '';
        })->count() > 0;
    }

    public function missingETA(): bool
    {
        return $this->lines->filter(function ($line) {
            return $line->promised_data < date('Y-m-d') && !$line->is_completed;
        })->count() > 0;
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

        if (!$this->isFullyInvoiced() || !$this->missingETA()) {
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

    public function getNotShippedValue()
    {
        if (!$this->lines) {
            return 0;
        }

        $total = 0;

        foreach ($this->lines as $line) {
            $total += ($line->is_shipped || $line->is_completed) ? 0 : ($line->quantity * $line->unit_cost);
        }

        return $total;
    }

    public function getArticlesNumbers()
    {
        if (!$this->lines) {
            return [];
        }

        $articleNumbers = [];

        foreach ($this->lines as $line) {
            $articleNumbers[] = $line->article_number;
        }

        return array_unique($articleNumbers);
    }

    public function calculateTotal(): void
    {
        $totalAmount = $this->lines->sum(function ($line) {
            return $line->unit_cost * $line->quantity;
        });

        $this->update([
            'amount' => $totalAmount,
        ]);
    }

    public function getEmptyShipment($purchaseOrderID = 0): ?PurchaseOrderShipment
    {
        $purchaseOrderID = intval($purchaseOrderID ?: $this->id);

        $shipments = PurchaseOrderShipment::where('purchase_order_id', '=', $purchaseOrderID)->get();
        if ($shipments->isEmpty()) return null;

        foreach ($shipments as $shipment) {
            if ($shipment->lines->isEmpty()) return $shipment;
        }

        return null;
    }
}
