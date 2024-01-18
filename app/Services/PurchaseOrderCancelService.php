<?php

namespace App\Services;

use App\Mail\PurchaseOrderCancellation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderCancelService
{
    /**
     * Deletes a purchase order
     *
     * @param PurchaseOrder $purchaseOrder
     * @return array
     */
    public function cancel(PurchaseOrder $purchaseOrder): array
    {
        // Send email to supplier that order is cancelled
        $recipients = preg_split("/[\s,;]+/", ($purchaseOrder->email ?: ($purchaseOrder->supplier->email ?? '')));
        $recipients = array_map('trim', $recipients);

        $recipients[] = 'ts@vendora.se';

        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (count($recipients) === 0) {
            return [false, 'No valid recipient email addresses found.'];
        }

        try {
            Mail::to($recipients)->queue(new PurchaseOrderCancellation($purchaseOrder));
        }
        catch (\Exception $e) {
            return [false, $e->getMessage()];
        }


        // Delete the local order (it will be synced later again)
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->delete();

        $purchaseOrder->delete();


        return ['success' => true, 'message' => ''];
    }
}
