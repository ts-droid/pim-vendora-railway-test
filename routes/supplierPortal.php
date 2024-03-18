<?php

use App\Http\Controllers\SupplierPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('/supplier-portal')->middleware('supplierPortal')->group(function() {

    Route::get('/purchase-order', [SupplierPortalController::class, 'index'])->name('supplierPortal.purchaseOrders.index');
    Route::get('/purchase-order/{purchaseOrder}/{hash}', [SupplierPortalController::class, 'order'])->name('supplierPortal.purchaseOrders.order');
    Route::post('/purchase-order/{purchaseOrder}/{hash}/confirm', [SupplierPortalController::class, 'confirm'])->name('supplierPortal.purchaseOrders.order.confirm');

});
