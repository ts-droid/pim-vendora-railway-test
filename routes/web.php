<?php

use App\Http\Controllers\EsignRecipientController;
use App\Http\Controllers\PurchaseOrderConfirmController;
use App\Http\Controllers\PurchaseOrderEtaController;
use App\Http\Controllers\PurchaseOrderPriceController;
use App\Http\Controllers\StatusCheckController;
use App\Http\Controllers\VismaNetTestController;
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

Route::get('/test', function() {
    $companySearch = new \App\Services\Allianz\AllianzCompanySearch();
    $gradeCover = new \App\Services\Allianz\AllianzGradeCover();


    $gradeCover->new();
    die();


    $jobID = 'ba37a6bb-1ea1-46bb-af32-49d2925a87d6';
    $gradeCover->checkStatus($jobID);
    die();


    $customer = $companySearch->searchCustomer('Dustin Aktiebolag', 'SE');

    if ($customer === null) {
        die('Failed to find customer.');
    }

    $companyID = $customer['companyId'];

    $jobID = $gradeCover->requestGradeCover($companyID);
    if ($jobID === null) {
        die('Failed to submit job grade cover request.');
    }

    dd($jobID);
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
    Route::get('/document/{document}/{secret}', [EsignRecipientController::class, 'document'])->name('esign.document');
    Route::get('/document/{document}/{secret}/sign', [EsignRecipientController::class, 'signDocument'])->name('esign.document.sign');
    Route::get('/document/{document}/{secret}/download', [EsignRecipientController::class, 'downloadDocument'])->name('esign.document.download');
});

Route::get('/status-check', [StatusCheckController::class, 'checkStatus']);

require __DIR__ . '/supplierPortal.php';

