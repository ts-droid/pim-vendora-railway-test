<?php

use App\Http\Controllers\SupplierPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('/supplier-portal')->middleware('supplierPortal')->group(function() {
    Route::get('/purchase-order', [SupplierPortalController::class, 'index'])->name('supplierPortal.purchaseOrders.index');
    Route::get('/purchase-order/{purchaseOrder}', [SupplierPortalController::class, 'order'])->name('supplierPortal.purchaseOrders.order');
    Route::post('/purchase-order/{purchaseOrder}', [SupplierPortalController::class, 'postOrder'])->name('supplierPortal.purchaseOrders.order.post');
    Route::post('/purchase-order/{purchaseOrder}/upload-invoice', [SupplierPortalController::class, 'uploadInvoice'])->name('supplierPortal.purchaseOrders.order.uploadInvoice');
});
