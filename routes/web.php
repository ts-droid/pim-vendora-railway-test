<?php

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ArticleSyncController;
use App\Http\Controllers\CustomerReviewController;
use App\Http\Controllers\EmailViewController;
use App\Http\Controllers\EsignPublicController;
use App\Http\Controllers\EsignRecipientController;
use App\Http\Controllers\MonitorDashboardController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\AdminArticleController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminSupplierController;
use App\Http\Controllers\PricingWebController;
use App\Http\Controllers\PurchaseOrderConfirmController;
use App\Http\Controllers\PurchaseOrderEtaController;
use App\Http\Controllers\PurchaseOrderPriceController;
use App\Http\Controllers\RawDataController;
use App\Http\Controllers\StatusCheckController;
use App\Http\Controllers\StockItemLogController;
use App\Http\Controllers\VismaNetTestController;;

use App\Jobs\UpdateArticleJob;
use App\Models\Article;
use App\Models\NewsletterSubscriber;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Services\AI\AIService;
use App\Services\AI\OpenAIService;
use App\Services\BrandPageService;
use App\Services\LanguageFieldTranslator;
use App\Services\ProductImageGenerator;
use App\Services\VismaNet\VismaNetSalesOrderService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([]);
});

Route::get('/test', function () {
    die();

    $json = file_get_contents(storage_path('purchase_order_lines.json'));
    $rows = json_decode($json, true);

    foreach ($rows as $row) {
        PurchaseOrderLine::where('purchase_order_id', $row['purchase_order_id'])
            ->where('line_key', $row['line_key'])
            ->where('article_number', $row['article_number'])
            ->update([
                'quantity' => $row['quantity'],
                'quantity_received' => $row['quantity_received'],
                'suggested_quantity' => $row['suggested_quantity'],
                'suggested_quantity_master' => $row['suggested_quantity_master'],
                'suggested_quantity_month' => $row['suggested_quantity_month'],
                'suggested_quantity_month_master' => $row['suggested_quantity_month_master'],
                'suggested_quantity_month_inner' => $row['suggested_quantity_month_inner'],
                'suggested_quantity_inner' => $row['suggested_quantity_inner'],
                'unit_cost' => $row['unit_cost'],
                'old_unit_cost' => $row['old_unit_cost'],
                'amount' => $row['amount'],
                'promised_shipping_date' => $row['promised_shipping_date'],
                'promised_date' => $row['promised_date'],
                'user_comment' => $row['user_comment'],
                'is_vip' => $row['is_vip'],
                'is_completed' => $row['is_completed'],
                'is_canceled' => $row['is_canceled'],
                'is_locked' => $row['is_locked'],
                'reminder_sent_at' => $row['reminder_sent_at'],
                'tracking_number' => $row['tracking_number'],
                'invoice_id' => $row['invoice_id'],
                'is_shipped' => $row['is_shipped'],
                'purchase_order_shipment_id' => $row['purchase_order_shipment_id'],
            ]);
    }

    echo 'Imported ' . count($rows) . ' rows';
    die();




    $json = file_get_contents(storage_path('purchase_orders.json'));
    $rows = json_decode($json, true);

    foreach ($rows as $row) {
        $orderNumber = $row['order_number'];

        \App\Models\PurchaseOrder::where('order_number', $orderNumber)->update([
            'promised_date' => $row['promised_date'],
            'is_draft' => $row['is_draft'],
            'is_vip' => $row['is_vip'],
            'foresight_days' => $row['foresight_days'],
            'email' => $row['email'],
            'published_at' => $row['published_at'],
            'is_sent' => $row['is_sent'],
            'is_confirmed' => $row['is_confirmed'],
            'is_po_system' => $row['is_po_system'],
            'status_sent_to_supplier' => $row['status_sent_to_supplier'],
            'status_sent_external' => $row['status_sent_external'],
            'status_confirmed_by_supplier' => $row['status_confirmed_by_supplier'],
            'status_shipping_details' => $row['status_shipping_details'],
            'status_tracking_number' => $row['status_tracking_number'],
            'status_invoice_uploaded' => $row['status_invoice_uploaded'],
            'status_received' => $row['status_received'],
            'confirm_reminder_sent_at' => $row['confirm_reminder_sent_at'],
            'shipping_reminder_sent_at' => $row['shipping_reminder_sent_at'],
            'invoice_reminder_sent_at' => $row['invoice_reminder_sent_at'],
            'supplier_order_number' => $row['supplier_order_number'],
            'shipping_instructions' => $row['shipping_instructions'],
            'is_direct' => $row['is_direct'],
            'direct_order' => $row['direct_order'],
        ]);
    }

    echo 'Imported ' . count($rows) . ' rows';
});

Route::get('/raw/article', [RawDataController::class, 'article'])->name('raw.article');

Route::get('/stock-logs', [StockItemLogController::class, 'index']);

Route::get('/sync-article', [ArticleSyncController::class, 'syncArticle']);
Route::get('/sync-all-article', [ArticleSyncController::class, 'syncAllArticles']);

Route::get('/fetch-sales-order', function() {
    $orderNumber = request('order_number');

    if ($orderNumber) {
        $vismaNetSalesOrderService = new VismaNetSalesOrderService();
        $response = $vismaNetSalesOrderService->fetchSalesOrder($orderNumber);

        dd($response);
    }
});

Route::get('/view-email/{email}', [EmailViewController::class, 'viewEmail']);
Route::get('/send-email/{email}', [EmailViewController::class, 'sendEmail']);

Route::prefix('/preview')->group(function () {
    Route::get('/sales-order/{salesOrder}/receipt', [PreviewController::class, 'receipt']);
    Route::get('/sales-order-receipt', [PreviewController::class, 'salesOrderReceipt']);
});

Route::prefix('/visma')->group(function() {
    Route::get('/status', function() {
        $vismaController = new \App\Http\Controllers\VismaNetController();

        die($vismaController->isActive() ? 'Integration is active.' : 'Integration is not active.');
    });

    Route::any('/activate', function() {
        $vismaController = new \App\Http\Controllers\VismaNetController();

        return redirect($vismaController->getAuthURL());
    })->name('visma.activate');

    Route::any('/callback', function(\Illuminate\Http\Request $request) {
        $vismaController = new \App\Http\Controllers\VismaNetController();

        list($activated, $message) = $vismaController->authCallback($request);

        if (!$activated) {
            die('FAILED: ' . $message);
        }

        die('SUCCESS: Integration activated!');
    })->name('visma.callback');

    Route::get('/test', [VismaNetTestController::class, 'index'])->name('visma.test');
    Route::post('/test', [VismaNetTestController::class, 'send'])->name('visma.test.send');
});

Route::prefix('/purchase-order')->group(function() {
    Route::get('/{purchaseOrder}/{hash}/confirm', [PurchaseOrderConfirmController::class, 'confirm'])->name('purchaseOrder.confirm');
    Route::post('/{purchaseOrder}/{hash}/confirm', [PurchaseOrderConfirmController::class, 'postConfirm'])->name('purchaseOrder.postConfirm');

    Route::get('/{purchaseOrder}/{hash}/eta', [PurchaseOrderEtaController::class, 'index'])->name('purchaseOrder.eta');
    Route::post('/{purchaseOrder}/{hash}/eta', [PurchaseOrderEtaController::class, 'post'])->name('purchaseOrder.postEta');

    Route::any('/{purchaseOrder}/{hash}/prices-confirm', [PurchaseOrderPriceController::class, 'confirm'])->name('purchaseOrder.pricesConfirm');
    Route::any('/{purchaseOrder}/{hash}/prices-reject', [PurchaseOrderPriceController::class, 'reject'])->name('purchaseOrder.pricesReject');
});

Route::prefix('/e-sign')->group(function() {
    Route::get('/document/{document}/preview/{accessHash}', [EsignPublicController::class, 'preview'])->name('esign.preview');
    Route::get('/document/{document}/{secret}', [EsignRecipientController::class, 'document'])->name('esign.document');
    Route::post('/document/{document}/{secret}/sign', [EsignRecipientController::class, 'signDocument'])->name('esign.document.sign');
    Route::get('/document/{document}/{secret}/download', [EsignRecipientController::class, 'downloadDocument'])->name('esign.document.download');
});

Route::get('/customer-review', [CustomerreviewController::class, 'index'])->name('customer.review');
Route::post('/customer-review', [CustomerreviewController::class, 'submit'])->name('customer.review.submit');
Route::get('/customer-review/done', [CustomerreviewController::class, 'done'])->name('customer.review.done');

Route::get('/status-check', [StatusCheckController::class, 'checkStatus']);

Route::get('/monitors', [MonitorDashboardController::class, 'index']);

Route::get('/sales-order/{salesOrder}/receipt', [PreviewController::class, 'salesOrderReceiptPublic'])->name('salesOrder.receipt');

Route::get('/pricing/{articleNumber}', [PricingWebController::class, 'calculator'])->name('pricing.calculator');
Route::get('/admin', [App\Http\Controllers\AdminIndexController::class, 'home'])->name('admin.index');
Route::get('/admin/articles', [App\Http\Controllers\AdminIndexController::class, 'articles'])->name('admin.articles.list');
Route::get('/admin/suppliers', [App\Http\Controllers\AdminIndexController::class, 'suppliers'])->name('admin.suppliers.list');
Route::get('/admin/customers', [App\Http\Controllers\AdminIndexController::class, 'customers'])->name('admin.customers.list');
Route::get('/admin/brands', [App\Http\Controllers\AdminIndexController::class, 'brands'])->name('admin.brands.list');

Route::get('/admin/articles/{articleNumber}', [AdminArticleController::class, 'show'])->name('admin.article.show');
Route::post('/admin/articles/{articleNumber}/pricing', [AdminArticleController::class, 'updatePricing'])->name('admin.article.update-pricing');
Route::post('/admin/articles/{articleNumber}/bid/toggle', [AdminArticleController::class, 'toggleBid'])->name('admin.article.bid.toggle');
Route::post('/admin/articles/{articleNumber}/bid/variants', [AdminArticleController::class, 'addBidVariant'])->name('admin.article.bid.variants.add');
Route::post('/admin/articles/{articleNumber}/bid/variants/{variantId}', [AdminArticleController::class, 'updateBidVariant'])->name('admin.article.bid.variants.update');
Route::post('/admin/articles/{articleNumber}/bid/variants/{variantId}/delete', [AdminArticleController::class, 'deleteBidVariant'])->name('admin.article.bid.variants.delete');
Route::post('/admin/articles/{articleNumber}/bundle/components', [AdminArticleController::class, 'addBundleComponent'])->name('admin.article.bundle.components.add');
Route::post('/admin/articles/{articleNumber}/bundle/components/{componentId}', [AdminArticleController::class, 'updateBundleComponent'])->name('admin.article.bundle.components.update');
Route::post('/admin/articles/{articleNumber}/bundle/components/{componentId}/delete', [AdminArticleController::class, 'deleteBundleComponent'])->name('admin.article.bundle.components.delete');
Route::post('/admin/articles/{articleNumber}/bundle/generate-gtin', [AdminArticleController::class, 'generateGTIN'])->name('admin.article.bundle.gtin');
Route::post('/admin/articles/{articleNumber}/create-bundle', [AdminArticleController::class, 'createBundleFromArticle'])->name('admin.article.create-bundle');
Route::post('/admin/articles/{articleNumber}/supports', [AdminArticleController::class, 'addSupport'])->name('admin.article.supports.add');
Route::post('/admin/articles/{articleNumber}/supports/{supportId}', [AdminArticleController::class, 'updateSupport'])->name('admin.article.supports.update');
Route::post('/admin/articles/{articleNumber}/supports/{supportId}/delete', [AdminArticleController::class, 'deleteSupport'])->name('admin.article.supports.delete');
Route::get('/admin/suppliers/{supplierNumber}', [AdminSupplierController::class, 'show'])->name('admin.supplier.show');
Route::get('/admin/customers/{customerNumber}', [AdminCustomerController::class, 'show'])->name('admin.customer.show');
Route::get('/admin/brands/{brandName}', [App\Http\Controllers\AdminBrandController::class, 'show'])
    ->where('brandName', '[^/]+')
    ->name('admin.brand.show');
Route::post('/admin/brands/{brandName}', [App\Http\Controllers\AdminBrandController::class, 'update'])
    ->where('brandName', '[^/]+')
    ->name('admin.brand.update');
Route::post('/admin/brands/{brandName}/rules', [App\Http\Controllers\AdminBrandController::class, 'addRule'])
    ->where('brandName', '[^/]+')
    ->name('admin.brand.rules.add');
Route::post('/admin/brands/{brandName}/rules/{ruleId}', [App\Http\Controllers\AdminBrandController::class, 'updateRule'])
    ->where('brandName', '[^/]+')
    ->name('admin.brand.rules.update');
Route::post('/admin/brands/{brandName}/rules/{ruleId}/delete', [App\Http\Controllers\AdminBrandController::class, 'deleteRule'])
    ->where('brandName', '[^/]+')
    ->name('admin.brand.rules.delete');

require __DIR__ . '/supplierPortal.php';

require __DIR__ . '/jobMonitor.php';

