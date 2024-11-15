<?php

namespace App\Services;

use App\Mail\PurchaseOrderCancellation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\VismaNet\VismaNetApiService;
use App\Utilities\PurchaseOrderHelper;
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

        $recipients = array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        $recipients = array_merge($recipients, PurchaseOrderHelper::getCCRecipients());

        if (count($recipients) === 0) {
            return [false, 'No valid recipient email addresses found.'];
        }

        try {
            Mail::to($recipients)->queue(new PurchaseOrderCancellation($purchaseOrder));
        }
        catch (\Exception $e) {
            return [false, $e->getMessage()];
        }


        // Delete lines in Visma.net
        // Visma.net API does not support deleting the whole order, so we have to delete each line separately
        $data = ['lines' => []];

        $purchaseOrderLines = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->get();
        foreach ($purchaseOrderLines as $purchaseOrderLine) {
            $data['lines'][] = [
                'operation' => 'Delete',
                'lineNumber' => ['value' => $purchaseOrderLine->line_key]
            ];
        }

        $vismaNetService = new VismaNetApiService();
        $vismaNetService->callAPI('PUT', '/v1/purchaseorder/' . $purchaseOrder->order_number, $data);

        // Delete the local order (it will be synced later again)
        PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->delete();
        $purchaseOrder->delete();


        return ['success' => true, 'message' => ''];
    }
}
