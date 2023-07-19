<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Reports\ArticleSalesController;
use App\Http\Controllers\Reports\SalesDataController;
use App\Http\Controllers\Reports\TopArticlesController;
use App\Http\Controllers\Reports\TopCustomersController;
use App\Http\Controllers\Reports\TopSalesPersonsController;
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

    Route::prefix('/articles')->group(function() {
        Route::get('/', [ArticleController::class, 'get'])->name('articles.get');
        Route::post('/', [ArticleController::class, 'store'])->name('articles.store');
        Route::post('/{article}', [ArticleController::class, 'update'])->name('articles.update');
    });

    Route::prefix('/customer-invoices')->group(function() {
        Route::get('/', [CustomerInvoiceController::class, 'get'])->name('customerInvoices.get');
        Route::post('/', [CustomerInvoiceController::class, 'store'])->name('customerInvoices.store');
        Route::post('/{customerInvoice}', [CustomerInvoiceController::class, 'update'])->name('customerInvoices.update');
    });

    Route::prefix('/purchase-orders')->group(function() {
        Route::get('/', [PurchaseOrderController::class, 'get'])->name('purchaseOrders.get');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('purchaseOrders.store');
        Route::post('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchaseOrders.update');
    });

    Route::prefix('/inventory-receipts')->group(function() {
        Route::get('/', [InventoryReceiptController::class, 'get'])->name('inventoryReceipts.get');
        Route::post('/', [InventoryReceiptController::class, 'store'])->name('inventoryReceipts.store');
        Route::post('/{inventoryReceipt}', [InventoryReceiptController::class, 'update'])->name('inventoryReceipts.update');
    });

    Route::prefix('/reports')->group(function() {
        Route::get('/sales-data', [SalesDataController::class, 'index'])->name('reports.salesData');
        Route::get('/article-sales', [ArticleSalesController::class, 'index'])->name('reports.articleSales');
        Route::get('/top-articles', [TopArticlesController::class, 'index'])->name('reports.topArticles');
        Route::get('/top-customers', [TopCustomersController::class, 'index'])->name('reports.topCustomers');
        Route::get('/top-sales-persons', [TopSalesPersonsController::class, 'index'])->name('reports.topSalesPersons');
    });

});
