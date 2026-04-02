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

require __DIR__ . '/supplierPortal.php';

require __DIR__ . '/jobMonitor.php';

