<?php

use App\Http\Controllers\ApiArticleCategoryController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePriceListController;
use App\Http\Controllers\ArticleTagController;
use App\Http\Controllers\ArtisanCommandController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\InventoryTurnoverController;
use App\Http\Controllers\LanguageApiController;
use App\Http\Controllers\MarketingContentController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PaymentReportController;
use App\Http\Controllers\ProductSeoController;
use App\Http\Controllers\PromptAPIController;
use App\Http\Controllers\PurchaseHistoryController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Reports\ArticleSalesController;
use App\Http\Controllers\Reports\SalesDataController;
use App\Http\Controllers\Reports\TopArticlesController;
use App\Http\Controllers\Reports\TopCustomersController;
use App\Http\Controllers\Reports\TopSalesPersonsController;
use App\Http\Controllers\SalesDashboardController;
use App\Http\Controllers\SalesPersonController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StatusIndicatorController;
use App\Http\Controllers\StockLogController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPriceController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\VismaNetApiController;
use App\Http\Controllers\VismaNetQueueController;
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

    Route::prefix('/commands')->group(function() {
        Route::post('/artisan', [ArtisanCommandController::class, 'run'])->name('commands.artisan');
        Route::post('/artisan/queue', [ArtisanCommandController::class, 'queue'])->name('commands.artisan.queue');
    });

    Route::prefix('/config')->group(function() {
        Route::get('/get', [ConfigController::class, 'getConfigRequest'])->name('config.getConfig');
        Route::post('/set', [ConfigController::class, 'setConfigRequest'])->name('config.setConfigs');
    });

    Route::prefix('/languages')->group(function() {
        Route::get('/get/all', [LanguageApiController::class, 'getAll'])->name('languages.getAll');
        Route::get('/get/active', [LanguageApiController::class, 'getActive'])->name('languages.getActive');
        Route::get('/get/{languageCode}', [LanguageApiController::class, 'getByCode'])->name('languages.getByCode');
        Route::any('/activate/{languageCode}', [LanguageApiController::class, 'activateLanguage'])->name('languages.activateLanguage');
        Route::any('/deactivate/{languageCode}', [LanguageApiController::class, 'deactivateLanguage'])->name('languages.deactivateLanguage');
        Route::post('/create', [LanguageApiController::class, 'createLanguage'])->name('languages.createLanguage');
    });

    Route::prefix('/newsletter')->group(function() {
        Route::get('/', [NewsletterController::class, 'get'])->name('newsletter.get');
        Route::post('/', [NewsletterController::class, 'store'])->name('newsletter.store');
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
        Route::get('/top-list', [CustomerController::class, 'topList'])->name('customers.topList');
        Route::post('/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::prefix('/sales-persons')->group(function() {
        Route::get('/', [SalesPersonController::class, 'get'])->name('salesPersons.get');
        Route::post('/', [SalesPersonController::class, 'store'])->name('salesPersons.store');
        Route::get('/budget', [SalesPersonController::class, 'allBudget'])->name('salesPersons.allBudget');
        Route::get('/{salesPerson}', [SalesPersonController::class, 'getOne'])->name('salesPersons.getOne');
        Route::post('/{salesPerson}', [SalesPersonController::class, 'update'])->name('salesPersons.update');
        Route::get('/{salesPerson}/budget', [SalesPersonController::class, 'budget'])->name('salesPersons.budget');
        Route::post('/{salesPerson}/budget', [SalesPersonController::class, 'saveBudget'])->name('salesPersons.saveBudget');
    });

    Route::prefix('/marketing-content')->group(function() {
        Route::prefix('/article')->group(function() {
            Route::get('/', [MarketingContentController::class, 'articleGet'])->name('marketingContent.article.get');
            Route::post('/', [MarketingContentController::class, 'articleStore'])->name('marketingContent.article.store');
            Route::post('/{articleMarketingContent}', [MarketingContentController::class, 'articleUpdate'])->name('marketingContent.article.update');
            Route::POST('/{articleMarketingContent}/stream', [MarketingContentController::class, 'articleStream'])->name('marketingContent.article.stream');
            Route::post('/{articleMarketingContent}/delete', [MarketingContentController::class, 'articleDelete'])->name('marketingContent.article.delete');
        });

        Route::post('/blog-post', [MarketingContentController::class, 'blogPostStream']);
        Route::post('/review-post', [MarketingContentController::class, 'reviewPostStream']);
    });

    Route::prefix('/suppliers')->group(function() {
        Route::get('/', [SupplierController::class, 'get'])->name('suppliers.get');
        Route::post('/', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::post('/update-many', [SupplierController::class, 'updateMany'])->name('suppliers.updateMany');
        Route::post('/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    Route::prefix('/supplier-prices')->group(function() {
        Route::post('/', [SupplierPriceController::class, 'store'])->name('supplierPrice.store');
    });

    Route::prefix('/articles')->group(function() {
        Route::get('/', [ArticleController::class, 'get'])->name('articles.get');
        Route::post('/', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('/basic', [ArticleController::class, 'getBasic'])->name('articles.getBasic');
        Route::get('/simple', [ArticleController::class, 'getSimple'])->name('articles.getSimple');
        Route::post('/update-many', [ArticleController::class, 'updateMany'])->name('articles.updateMany');
        Route::post('/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::get('/{article}/retailers', [ArticleController::class, 'getRetailers'])->name('articles.getRetailers');
        Route::get('/{article}/categories', [ArticleController::class, 'getCategories'])->name('articles.getCategories');

        Route::get('/images', [ArticleController::class, 'getAllImages'])->name('articles.getAllImages');

        Route::get('/{article}/images', [ArticleController::class, 'getImages'])->name('articles.getImages');
        Route::post('/{article}/images/{articleImage}', [ArticleController::class, 'updateImage'])->name('articles.updateImage');
        Route::post('/{article}/images/{articleImage}/solid', [ArticleController::class, 'updateImageSolid'])->name('articles.updateImageSolid');
    });

    Route::prefix('/article-categories')->group(function() {
        Route::get('/', [ApiArticleCategoryController::class, 'getAll']);
        Route::post('/', [ApiArticleCategoryController::class, 'store']);
        Route::get('/{articleCategory}', [ApiArticleCategoryController::class, 'get']);
        Route::post('/{articleCategory}', [ApiArticleCategoryController::class, 'update']);
        Route::post('/{articleCategory}/connect', [ApiArticleCategoryController::class, 'connect']);
        Route::post('/{articleCategory}/disconnect', [ApiArticleCategoryController::class, 'disconnect']);
    });

    Route::prefix('/article-tags')->group(function() {
        Route::get('/', [ArticleTagController::class, 'get'])->name('articleTags.get');
        Route::post('/', [ArticleTagController::class, 'store'])->name('articleTags.store');
        Route::get('/connections', [ArticleTagController::class, 'connections'])->name('articleTags.connections');
        Route::get('/{articleTag}', [ArticleTagController::class, 'getTag'])->name('articleTags.getTag');
        Route::post('/{articleTag}', [ArticleTagController::class, 'update'])->name('articleTags.update');
        Route::post('/{articleTag}/delete', [ArticleTagController::class, 'delete'])->name('articleTags.delete');
        Route::post('/{articleTag}/connect/{article}', [ArticleTagController::class, 'connect'])->name('articleTags.connect');
        Route::post('/{articleTag}/disconnect/{article}', [ArticleTagController::class, 'disconnect'])->name('articleTags.disconnect');
    });

    Route::prefix('/customer-invoices')->group(function() {
        Route::get('/', [CustomerInvoiceController::class, 'get'])->name('customerInvoices.get');
        Route::post('/', [CustomerInvoiceController::class, 'store'])->name('customerInvoices.store');
        Route::post('/{customerInvoice}', [CustomerInvoiceController::class, 'update'])->name('customerInvoices.update');
    });

    Route::prefix('/purchase-orders')->group(function() {
        Route::get('/', [PurchaseOrderController::class, 'get'])->name('purchaseOrders.get');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('purchaseOrders.store');
        Route::get('/ongoing', [PurchaseOrderController::class, 'getOngoing'])->name('purchaseOrders.getOngoing');
        Route::get('/ongoing-sent', [PurchaseOrderController::class, 'getOngoingSent'])->name('purchaseOrders.getOngoingSent');
        Route::get('/ongoing-deleted', [PurchaseOrderController::class, 'getOngoingDeleted'])->name('purchaseOrders.getOngoingDeleted');
        Route::post('/send-reminders', [PurchaseOrderController::class, 'sendReminders'])->name('purchaseOrders.sendReminders');
        Route::post('/cancel', [PurchaseOrderController::class, 'cancelOrderLines'])->name('purchaseOrders.cancelOrderLines');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'getOrder'])->name('purchaseOrders.getOrder');
        Route::post('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchaseOrders.update');
        Route::post('/{purchaseOrder}/add-row', [PurchaseOrderController::class, 'addRow'])->name('purchaseOrders.addRow');
        Route::post('/{purchaseOrder}/regenerate', [PurchaseOrderController::class, 'regenerate'])->name('purchaseOrders.regenerate');
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchaseOrders.send');
        Route::post('/{purchaseOrder}/publish', [PurchaseOrderController::class, 'publish'])->name('purchaseOrders.publish');
        Route::post('/{purchaseOrder}/delete', [PurchaseOrderController::class, 'delete'])->name('purchaseOrders.delete');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchaseOrders.cancel');
        Route::post('/{purchaseOrder}/user-delete', [PurchaseOrderController::class, 'userDelete'])->name('purchaseOrders.userDelete');
        Route::post('/{purchaseOrder}/draft-reminder', [PurchaseOrderController::class, 'draftReminder'])->name('purchaseOrders.draftReminder');
        Route::post('/{purchaseOrder}/copy-line', [PurchaseOrderController::class, 'copyLine'])->name('purchaseOrders.copyLine');
    });

    Route::prefix('/inventory-receipts')->group(function() {
        Route::get('/', [InventoryReceiptController::class, 'get'])->name('inventoryReceipts.get');
        Route::post('/', [InventoryReceiptController::class, 'store'])->name('inventoryReceipts.store');
        Route::post('/{inventoryReceipt}', [InventoryReceiptController::class, 'update'])->name('inventoryReceipts.update');
    });

    Route::prefix('/visma-net')->group(function() {
        Route::get('/shipment', [VismaNetApiController::class, 'getShipment'])->name('vismanet.getShipment');
        Route::get('/customer', [VismaNetApiController::class, 'getCustomer'])->name('vismanet.getCustomer');
        Route::get('/inventory-item', [VismaNetApiController::class, 'getInventoryItem'])->name('vismanet.getInventoryItem');
        Route::get('/sales-order', [VismaNetApiController::class, 'getSalesOrder'])->name('vismanet.getSalesOrder');

        Route::post('/queue', [VismaNetQueueController::class, 'queue'])->name('vismanet.queue.insert');
    });

    Route::prefix('/stock-log')->group(function() {
        Route::get('/', [StockLogController::class, 'get'])->name('stockLog.get');
    });

    Route::prefix('/price-list')->group(function() {
        Route::get('/customer', [ArticlePriceListController::class, 'customer'])->name('priceList.customer');
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

        Route::get('/payment-report', [PaymentReportController::class, 'index']);

        Route::get('/inventory-turnover', [InventoryTurnoverController::class, 'index']);
        Route::get('/inventory-turnover/article', [InventoryTurnoverController::class, 'article']);
        Route::get('/inventory-turnover/brands', [InventoryTurnoverController::class, 'brands']);

        Route::get('/purchase-history', [PurchaseHistoryController::class, 'index']);

        Route::get('/sales-dashboard', [SalesDashboardController::class, 'index']);
        Route::get('/sales-dashboard/summary', [SalesDashboardController::class, 'summary']);
        Route::get('/sales-dashboard/suggestions', [SalesDashboardController::class, 'suggestions']);
        Route::post('/sales-dashboard/suggestions/complete', [SalesDashboardController::class, 'suggestionsComplete']);
        Route::get('/sales-dashboard/intel', [SalesDashboardController::class, 'intel']);
        Route::post('/sales-dashboard/intel/complete', [SalesDashboardController::class, 'intelComplete']);
        Route::get('/sales-dashboard/eol', [SalesDashboardController::class, 'eol']);
    });

    Route::prefix('/product-seo')->group(function() {
        Route::post('/meta-data/queue', [ProductSeoController::class, 'queueMetaData']);
        Route::post('/meta-data/queue/brand', [ProductSeoController::class, 'queueBrandMetaData']);

        Route::post('/images/queue', [ProductSeoController::class, 'queueImageData']);
        Route::post('/images/queue/brand', [ProductSeoController::class, 'queueBrandImageData']);
    });

});
