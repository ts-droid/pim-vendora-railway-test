<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\LanguageApiController;
use App\Http\Controllers\MarketingContentController;
use App\Http\Controllers\PromptAPIController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Reports\ArticleSalesController;
use App\Http\Controllers\Reports\SalesDataController;
use App\Http\Controllers\Reports\TopArticlesController;
use App\Http\Controllers\Reports\TopCustomersController;
use App\Http\Controllers\Reports\TopSalesPersonsController;
use App\Http\Controllers\SalesPersonController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\StockLogController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TranslationController;
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

Route::prefix('/v1')->middleware(['api.key', 'gzip'])->group(function() {

    Route::prefix('/languages')->group(function() {
        Route::get('/get/all', [LanguageApiController::class, 'getAll'])->name('languages.getAll');
        Route::get('/get/active', [LanguageApiController::class, 'getActive'])->name('languages.getActive');
        Route::get('/get/{languageCode}', [LanguageApiController::class, 'getByCode'])->name('languages.getByCode');
        Route::any('/activate/{languageCode}', [LanguageApiController::class, 'activateLanguage'])->name('languages.activateLanguage');
        Route::any('/deactivate/{languageCode}', [LanguageApiController::class, 'deactivateLanguage'])->name('languages.deactivateLanguage');
        Route::post('/create', [LanguageApiController::class, 'createLanguage'])->name('languages.createLanguage');
    });

    Route::prefix('/prompt')->group(function() {
        Route::post('/execute', [PromptAPIController::class, 'execute'])->name('prompt.execute');
        Route::post('/get-executable', [PromptAPIController::class, 'getExecutable'])->name('prompt.getExecutable');
        Route::post('/store', [PromptAPIController::class, 'store'])->name('prompt.store');
        Route::get('/get', [PromptAPIController::class, 'getAll'])->name('prompt.getAll');
        Route::get('/get/{prompt}', [PromptAPIController::class, 'get'])->name('prompt.get');
        Route::get('/get-system-code', [PromptAPIController::class, 'getBySystemCode'])->name('prompt.getBySystemCode');
        Route::get('/group', [PromptAPIController::class, 'getGroup'])->name('prompt.getGroup');
    });

    Route::prefix('/translate')->group(function() {
        Route::post('/', [TranslationController::class, 'translateRequest'])->name('translate');
    });

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

    Route::prefix('/marketing-content')->group(function() {
        Route::prefix('/article')->group(function() {
            Route::get('/', [MarketingContentController::class, 'articleGet'])->name('marketingContent.article.get');
            Route::post('/', [MarketingContentController::class, 'articleStore'])->name('marketingContent.article.store');
            Route::post('/{articleMarketingContent}', [MarketingContentController::class, 'articleUpdate'])->name('marketingContent.article.update');
            Route::post('/{articleMarketingContent}/delete', [MarketingContentController::class, 'articleDelete'])->name('marketingContent.article.delete');
        });
    });

    Route::prefix('/suppliers')->group(function() {
        Route::get('/', [SupplierController::class, 'get'])->name('suppliers.get');
        Route::post('/', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::post('/update-many', [SupplierController::class, 'updateMany'])->name('suppliers.updateMany');
        Route::post('/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    Route::prefix('/articles')->group(function() {
        Route::get('/', [ArticleController::class, 'get'])->name('articles.get');
        Route::post('/', [ArticleController::class, 'store'])->name('articles.store');
        Route::post('/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::get('/{article}/retailers', [ArticleController::class, 'getRetailers'])->name('articles.getRetailers');

        Route::get('/{article}/images', [ArticleController::class, 'getImages'])->name('articles.getImages');
        Route::post('/{article}/images/{articleImage}', [ArticleController::class, 'updateImage'])->name('articles.updateImage');
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

    Route::prefix('/shipments')->group(function() {
        Route::get('/visma', [ShipmentController::class, 'getVisma'])->name('shipments.getVisma');
    });

    Route::prefix('/stock-log')->group(function() {
        Route::get('/', [StockLogController::class, 'get'])->name('stockLog.get');
    });

    Route::prefix('/status-indicators')->group(function() {
        Route::get('/', [StatusIndicatorController::class, 'getAll']);
        Route::post('/ping', [StatusIndicatorController::class, 'pingRequest']);
    });

    Route::prefix('/reports')->group(function() {
        Route::get('/sales-data', [SalesDataController::class, 'index'])->name('reports.salesData');
        Route::get('/article-sales', [ArticleSalesController::class, 'index'])->name('reports.articleSales');
        Route::get('/top-articles', [TopArticlesController::class, 'index'])->name('reports.topArticles');
        Route::get('/top-customers', [TopCustomersController::class, 'index'])->name('reports.topCustomers');
        Route::get('/top-sales-persons', [TopSalesPersonsController::class, 'index'])->name('reports.topSalesPersons');
    });

});
