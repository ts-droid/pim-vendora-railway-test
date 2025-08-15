<?php

use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupplierPortalQrController;
use Illuminate\Support\Facades\Route;

Route::prefix('/supplier-portal')->middleware('supplierPortal')->group(function() {
    Route::get('/purchase-order', [SupplierPortalController::class, 'index'])->name('supplierPortal.purchaseOrders.index');
    Route::get('/purchase-order/{purchaseOrder}', [SupplierPortalController::class, 'order'])->name('supplierPortal.purchaseOrders.order');
    Route::post('/purchase-order/{purchaseOrder}', [SupplierPortalController::class, 'postOrder'])->name('supplierPortal.purchaseOrders.order.post');
    Route::post('/purchase-order/{purchaseOrder}/invoice', [SupplierPortalController::class, 'uploadInvoice'])->name('supplierPortal.purchaseOrders.order.uploadInvoice');
    Route::get('/purchase-order/{purchaseOrder}/invoice/{supplierInvoice}/delete', [SupplierPortalController::class, 'deleteInvoice'])->name('supplierPortal.purchaseOrders.order.deleteInvoice');
    Route::post('/purchase-order/{purchaseOrder}/shipment', [SupplierPortalController::class, 'createShipment'])->name('supplierPortal.purchaseOrders.order.createShipment');

    Route::get('/qr-code/print', [SupplierPortalQrController::class, 'print'])->name('supplierPortal.qrCode.print');
    Route::get('/qr-code/download', [SupplierPortalQrController::class, 'download'])->name('supplierPortal.qrCode.download');
});
