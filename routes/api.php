<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesPersonController;
use App\Http\Controllers\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('/v1')->middleware(['api.key'])->group(function() {

    Route::prefix('/customers')->group(function() {
        Route::get('/', [CustomerController::class, 'get'])->name('customers.get');
        Route::post('/', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::prefix('/sales-persons')->group(function() {
        Route::get('/', [SalesPersonController::class, 'get'])->name('salesPersons.get');
        Route::post('/', [SalesPersonController::class, 'store'])->name('salesPersons.store');
        Route::post('/{salesPerson}', [SalesPersonController::class, 'update'])->name('salesPersons.update');
    });

    Route::prefix('/suppliers')->group(function() {
        Route::get('/', [SupplierController::class, 'get'])->name('suppliers.get');
        Route::post('/', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::post('/{salesPerson}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

});
