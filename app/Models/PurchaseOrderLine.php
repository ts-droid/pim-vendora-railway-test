<?php

namespace App\Models;

use App\Services\PurchaseOrderPublisher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'line_key',
        'article_number',
        'description',
        'quantity',
        'quantity_received',
        'suggested_quantity',
        'suggested_quantity_master',
        'suggested_quantity_inner',
        'suggested_quantity_month',
        'suggested_quantity_month_master',
        'suggested_quantity_month_inner',
        'unit_cost',
        'old_unit_cost',
        'amount',
        'promised_date',
        'ai_comment',
        'user_comment',
        'is_vip',
        'is_completed',
        'is_canceled',
        'is_locked',
        'reminder_sent_at',
        'tracking_number',
        'invoice_id',
        'is_shipped',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number');
    }

    public function invoice()
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id', 'id');
    }

    public function getShippingDate()
    {
        $shippingDateBuffer = ($this->purchaseOrder->supplier->general_delivery_time) ?: PurchaseOrderPublisher::SHIPPING_DATE_BUFFER;

        if ($this->promised_date) {
            return date('Y-m-d', strtotime($this->promised_date) - (86400 * $shippingDateBuffer));
        }

        return '';
    }
}
