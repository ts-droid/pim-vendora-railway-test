<?php

use App\Http\Controllers\SupplierPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('/supplier-portal')->group(function() {

    Route::get('/', [SupplierPortalController::class, 'index'])->name('supplierPortal');

});
