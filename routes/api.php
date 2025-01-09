<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\ApiArticleCategoryController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePriceListController;
use App\Http\Controllers\ArticleReviewController;
use App\Http\Controllers\ArticleTagController;
use App\Http\Controllers\ArtisanCommandController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\EcbController;
use App\Http\Controllers\EsignController;
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
use App\Http\Controllers\StockOptimizationController;
use App\Http\Controllers\StockPlaceController;
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

Route::prefix('/v2')->middleware(['api.key', 'gzip'])->group(function() {
    Route::prefix('/articles')->group(function() {
        Route::post('/', [ArticleController::class, 'storeV2'])->name('articles.store.v2');
        Route::post('/{article}', [ArticleController::class, 'updateV2'])->name('articles.update.v2');
    });
});

Route::prefix('/v1')->middleware(['api.key', 'gzip'])->group(function() {

    Route::get('/ecb/convert-currency', [EcbController::class, 'convertCurrency']);

    Route::prefix('/wms')->group(function() {
        Route::get('/stock-places', [StockPlaceController::class, 'getStockPlaces']);
        Route::post('/stock-places', [StockPlaceController::class, 'storeStockPlace']);
        Route::get('/stock-places/compartment-templates', [StockPlaceController::class, 'getCompartmentTemplates']);

        Route::get('/stock-places/detailed', [StockPlaceController::class, 'getDetailedStockPlaces']);

        Route::get('/stock-places/groups', [StockPlaceController::class, 'getStockPlaceGroups']);
        Route::post('/stock-places/groups', [StockPlaceController::class, 'storeStockPlaceGroups']);
        Route::post('/stock-places/groups/{stockPlaceGroup}', [StockPlaceController::class, 'updateStockPlaceGroup']);
        Route::post('/stock-places/groups/{stockPlaceGroup}/delete', [StockPlaceController::class, 'deleteStockPlaceGroup']);

        Route::get('/stock-places/{stockPlace}', [StockPlaceController::class, 'getStockPlace']);
        Route::post('/stock-places/{stockPlace}', [StockPlaceController::class, 'updateStockPlace']);
        Route::post('/stock-places/{stockPlace}/delete', [StockPlaceController::class, 'deleteStockPlace']);
        Route::post('/stock-places/{stockPlace}/compartments', [StockPlaceController::class, 'storeStockPlaceCompartment']);
        Route::post('/stock-places/{stockPlace}/compartments/{stockPlaceCompartment}', [StockPlaceController::class, 'updateStockPlaceCompartment']);
        Route::post('/stock-places/{stockPlace}/compartments/{stockPlaceCompartment}/delete', [StockPlaceController::class, 'deleteStockPlaceCompartment']);

        Route::post('/stock-places/{stockPlace}/compartments/{stockPlaceCompartment}/section', [StockPlaceController::class, 'storeCompartmentSection']);
        Route::post('/stock-places/{stockPlace}/compartments/{stockPlaceCompartment}/section/{compartmentSection}/delete', [StockPlaceController::class, 'deleteCompartmentSection']);

        Route::post('/optimization-stock', [StockOptimizationController::class, 'optimizeStock']);
    });

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
        Route::get('/engines', [TranslationController::class, 'getEngines']);
    });

    Route::prefix('/customers')->group(function() {
        Route::get('/', [CustomerController::class, 'get'])->name('customers.get');
        Route::post('/', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/top-list', [CustomerController::class, 'topList'])->name('customers.topList');
        Route::get('/{customer}', [CustomerController::class, 'getCustomer'])->name('customers.getCustomer');
        Route::get('/{customer}/sales', [CustomerController::class, 'getCustomerSales'])->name('customers.getCustomerSales');
        Route::get('/{customer}/allianz', [CustomerController::class, 'getCustomerAllianz'])->name('customers.getCustomerAllianz');
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

    Route::prefix('/ai')->group(function() {
        Route::post('/stream', [AIController::class, 'stream']);
    });

    Route::prefix('/marketing-content')->group(function() {
        Route::prefix('/article')->group(function() {
            Route::get('/', [MarketingContentController::class, 'articleGet'])->name('marketingContent.article.get');
            Route::post('/', [MarketingContentController::class, 'articleStore'])->name('marketingContent.article.store');
            Route::post('/{articleMarketingContent}', [MarketingContentController::class, 'articleUpdate'])->name('marketingContent.article.update');
            Route::post('/{articleMarketingContent}/stream', [MarketingContentController::class, 'articleStream'])->name('marketingContent.article.stream');
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

    Route::prefix('/article-reviews')->group(function() {
        Route::post('/', [ArticleReviewController::class, 'store'])->name('articleReview.store');
        Route::post('/{articleReview}', [ArticleReviewController::class, 'update'])->name('articleReview.update');
        Route::post('/{articleReview}/delete', [ArticleReviewController::class, 'delete'])->name('articleReview.delete');
    });

    Route::prefix('/articles')->group(function() {
        Route::get('/', [ArticleController::class, 'get'])->name('articles.get');
        Route::post('/', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('/search', [ArticleController::class, 'search'])->name('articles.search');
        Route::get('/basic', [ArticleController::class, 'getBasic'])->name('articles.getBasic');
        Route::get('/simple', [ArticleController::class, 'getSimple'])->name('articles.getSimple');
        Route::get('/brands', [ArticleController::class, 'getBrands'])->name('articles.getBrands');
        Route::get('/unspsc-categories', [ArticleController::class, 'unspscCategories'])->name('articles.unspscCategories');

        Route::get('/edit-data', [ArticleController::class, 'getEditData'])->name('articles.getEditData');

        Route::get('/images', [ArticleController::class, 'getAllImages'])->name('articles.getAllImages');
        Route::post('/images/setListOrder', [ArticleController::class, 'setImageListOrder'])->name('articles.setImageListOrder');

        Route::post('/update-many', [ArticleController::class, 'updateMany'])->name('articles.updateMany');
        Route::get('/{article}', [ArticleController::class, 'getArticle'])->name('articles.getArticle');
        Route::post('/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::get('/{article}/retailers', [ArticleController::class, 'getRetailers'])->name('articles.getRetailers');
        Route::get('/{article}/categories', [ArticleController::class, 'getCategories'])->name('articles.getCategories');
        Route::get('/{article}/reviews', [ArticleController::class, 'getReviews'])->name('articles.getReviews');
        Route::get('/{article}/wms-data', [ArticleController::class, 'getArticleWmsData'])->name('articles.getWmsData');

        Route::get('/{article}/files', [ArticleController::class, 'getFiles'])->name('articles.getFiles');
        Route::post('/{article}/files', [ArticleController::class, 'uploadFile'])->name('articles.uploadFile');
        Route::post('/{article}/files/{articleFile}/delete', [ArticleController::class, 'deleteFile'])->name('articles.deleteFile');

        Route::get('/{article}/images', [ArticleController::class, 'getImages'])->name('articles.getImages');
        Route::get('/{article}/images-basic', [ArticleController::class, 'getImagesBasic'])->name('articles.getImagesBasic');
        Route::post('/{article}/images', [ArticleController::class, 'uploadImage'])->name('articles.uploadImage');
        Route::post('/{article}/images/{articleImage}', [ArticleController::class, 'updateImage'])->name('articles.updateImage');
        Route::post('/{article}/images/{articleImage}/solid', [ArticleController::class, 'updateImageSolid'])->name('articles.updateImageSolid');
        Route::post('/{article}/images/{articleImage}/delete', [ArticleController::class, 'deleteImage'])->name('articles.deleteImage');

        Route::post('/{article}/package-images', [ArticleController::class, 'uploadPackageImages'])->name('articles.uploadPackageImages');

        Route::get('/{article}/sub-data', [ArticleController::class, 'getSubData'])->name('articles.getSubData');
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

    Route::prefix('/e-sign')->group(function() {
        Route::get('/templates', [EsignController::class, 'getTemplates']);
        Route::post('/templates', [EsignController::class, 'storeTemplate']);
        Route::get('/templates/{template}', [EsignController::class, 'getTemplate']);
        Route::post('/templates/{template}', [EsignController::class, 'updateTemplate']);
        Route::post('/templates/{template}/delete', [EsignController::class, 'deleteTemplate']);
        Route::post('/templates/{template}/sections', [EsignController::class, 'storeSection']);
        Route::post('/templates/{template}/sections/{section}', [EsignController::class, 'updateSection']);
        Route::any('/templates/{template}/sections/{section}/delete', [EsignController::class, 'deleteSection']);

        Route::get('/variables', [EsignController::class, 'getVariables']);
        Route::post('/variables', [EsignController::class, 'setVariables']);
        Route::get('/collectables', [EsignController::class, 'getCollectables']);
        Route::post('/collectables', [EsignController::class, 'setCollectables']);

        Route::get('/tabs', [EsignController::class, 'getTabs']);
        Route::post('/tabs', [EsignController::class, 'storeTab']);
        Route::post('/tabs/{tab}', [EsignController::class, 'updateTab']);
        Route::any('/tabs/{tab}/delete', [EsignController::class, 'deleteTab']);

        Route::get('/documents', [EsignController::class, 'getDocuments']);
        Route::post('/documents', [EsignController::class, 'storeDocument']);
        Route::post('/documents/upload', [EsignController::class, 'uploadDocument']);
        Route::get('/documents/{document}', [EsignController::class, 'getDocument']);
        Route::post('/documents/{document}', [EsignController::class, 'updateDocument']);
        Route::get('/documents/{document}/preview', [EsignController::class, 'previewDocument']);
        Route::post('/documents/{document}/send', [EsignController::class, 'sendDocument']);
        Route::any('/documents/{document}/delete', [EsignController::class, 'deleteDocument']);
        Route::post('/documents/{document}/recipients', [EsignController::class, 'addRecipient']);
        Route::post('/documents/{document}/recipients/{recipient}/set-main', [EsignController::class, 'setMainRecipient']);
        Route::any('/documents/{document}/recipients/{recipient}/delete', [EsignController::class, 'deleteRecipient']);
    });
});

require __DIR__ . '/app.php';
