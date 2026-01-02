<?php

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ArticleSyncController;
use App\Http\Controllers\CustomerReviewController;
use App\Http\Controllers\EmailViewController;
use App\Http\Controllers\EsignPublicController;
use App\Http\Controllers\EsignRecipientController;
use App\Http\Controllers\MonitorDashboardController;
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

Route::get('/image', function() {
    $product_description = 'PowerBug is the sleek, space-saving way to charge your iPhone and more. Snap your MagSafe or Qi2-compatible phone into place for hands-free charging and StandBy mode, while a high-power USB-C port fast-charges a second device. With foldable prongs and a compact footprint, it turns any outlet into an elegant charging dock at home or on the go.
<ul>
	<li>Turns any outlet into a magnetic charging dock</li>
	<li>35 W USB-C PD fast charging with PPS</li>
	<li>Charge two devices at once: 15 W wireless plus 20 W USB-C</li>
	<li>StandBy-ready magnetic mount for hands-free viewing</li>
	<li>Compact design with foldable Type A prongs</li>
</ul>
<strong>Turns any outlet into a magnetic charging dock</strong><br />
Lift your phone off the counter and clear the clutter. PowerBug magnetically holds your MagSafe or Qi2-compatible phone in view while it charges, transforming a plain wall outlet into a tidy, functional charging station for kitchens, bedrooms, offices, and more.<br />
<br />
<strong>35 W USB-C PD fast charging with PPS</strong><br />
Give your devices the power they deserve. The built-in USB-C port delivers up to 35 W with Power Delivery 3.0 and PPS support, providing fast, efficient charging for tablets, earbuds, and compatible smartphones.<br />
<br />
<strong>Charge two devices at once: 15 W wireless plus 20 W USB-C</strong><br />
Top up your iPhone wirelessly at up to 15 W while simultaneously powering a second device via USB-C at up to 20 W. It is an effortless 2-in-1 solution that keeps your daily carry ready without extra bricks or cables.<br />
<br />
<strong>StandBy-ready magnetic mount for hands-free viewing</strong><br />
Snap your iPhone into place and turn on iOS StandBy to see the time, photos, calendars, and widgets across the room. It is perfect for bedside alarms, cooking timers, quick FaceTime calls, and controlling smart home devices.<br />
<br />
<strong>Compact design with foldable Type A prongs</strong><br />
Designed for everyday spaces and easy travel, PowerBug&rsquo;s compact body and foldable prongs slip neatly into a bag or drawer. Universal 100 to 240 V input makes it a great companion at home or abroad with the appropriate plug adapter.<br />
<br />
<strong>Package includes</strong>

<ul>
	<li>PowerBug with Type A prong - Slate</li>
	<li>Manual</li>
</ul>
<strong>Product specifications</strong>

<ul>
	<li>Colour: Slate</li>
	<li>Dimensions: 60 x 60 x 29.18 mm</li>
	<li>Weight: 0.095 kg</li>
	<li>Input: 100 to 240 V, 50 to 60 Hz, 1.0 A max</li>
	<li>Wireless charging: Qi2 up to 15 W</li>
	<li>USB-C output: Up to 35 W, PD 3.0 and PPS</li>
	<li>Dual output: 20 W via USB-C plus 15 W via Qi2, 35 W max combined</li>
	<li>Compatibility: All Qi2 and MagSafe enabled smartphones</li>
	<li>USB-C devices: iPad, AirPods, Android phones and tablets, and more</li>
	<li>iOS StandBy mode supported</li>
	<li>Prongs: Foldable Type A design</li>
</ul>';

    $image_url = 'https://vendora.ams3.digitaloceanspaces.com/pim/1-TS_PowerBug-Slate.png';

    $description_prompt = 'Describe this image.';
    $setting_prompt = 'Jag ska göra en bild av den här produkten.
Jag har produktbilden, men jag vill ha den i olikasituationer, till exempel på ett skrivbord eller i hallen eller vad det nu än må vara.
Läs igenom texten nedan och gör en anpassa bild-prompt för att skapa en bild av produkten i en miljö som är relevant för produkten.';
    $generation_prompt = 'Skapa ett professionellt foto i utseende som man gör i en annons, fotostudio med proffskamera, perfekt ljussättning.
Bilden MÅSTE vara 1024x1024.';

    try {
        $productImageGenerator = new ProductImageGenerator();
        $imageBase64 = $productImageGenerator->generateLifestyleImage($product_description, $image_url, $description_prompt, $setting_prompt, $generation_prompt);
    } catch (\Throwable $e) {
        return ApiResponseController::error($e->getMessage());
    }

    dd($imageBase64);

});

Route::get('/test-titles', function () {
    $articleNumber = request()->get('article_number');
    $article = Article::where('article_number', $articleNumber)->first();

    if (!$article) {
        echo 'Article not found';
        return;
    }

    $job = new \App\Jobs\GenerateArticleTitles($article);
    $updates = $job->handle();

    $formattedUpdates = [];
    foreach ($updates as $key => $value) {
        if ($key == 'description') {
            $key = 'article_name';
        }

        $formattedUpdates[$key] = $value;
    }

    dd($formattedUpdates);
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

require __DIR__ . '/supplierPortal.php';

require __DIR__ . '/jobMonitor.php';

