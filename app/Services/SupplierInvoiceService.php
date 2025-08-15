<?php

namespace App\Services;

use App\Http\Controllers\DoSpacesController;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class SupplierInvoiceService
{
    public function uploadInvoice(PurchaseOrder $purchaseOrder, array $invoiceLineIDs, UploadedFile $file)
    {
        // Make sure all invoice line ID's is an integer
        $invoiceLineIDs = array_map('intval', $invoiceLineIDs);

        // Upload the invoice to the storage
        $spaceFilename = DoSpacesController::store(
            time() . '_' . $file->getClientOriginalName(),
            $file->getContent(),
            true,
        );

        $fileUrl = DoSpacesController::getURL($spaceFilename);

        // Store the invoice
        $supplierInvoice = SupplierInvoice::create([
            'purchase_order_id' => $purchaseOrder->id,
            'filename' => $spaceFilename,
            'client_filename' => $file->getClientOriginalName()
        ]);

        // Connect the invoice to the purchase order lines
        $purchaseOrder->lines()->whereIn('id', $invoiceLineIDs)->update([
            'invoice_id' => $supplierInvoice->id,
        ]);

        // Send email to Vendora with the invoice
        if (App::isLocal()) {
            Mail::to('anton@scriptsector.se')->send(
                new \App\Mail\SupplierInvoice($purchaseOrder, $invoiceLineIDs, $fileUrl)
            );
        } else {
            Mail::to('invoice@vendora.se')->queue(
                new \App\Mail\SupplierInvoice($purchaseOrder, $invoiceLineIDs, $fileUrl)
            );
        }
    }

    public function deleteInvoice(SupplierInvoice $supplierInvoice)
    {
        // Delete the invoice file from storage
        DoSpacesController::delete($supplierInvoice->filename);

        // Disconnect the invoice from the purchase order lines
        $supplierInvoice->lines()->update(['invoice_id' => 0]);

        // Delete the invoice record
        $supplierInvoice->delete();
    }
}
