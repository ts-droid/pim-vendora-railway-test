<?php

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

        $activated = $vismaController->authCallback($request);

        die($activated ? 'Visma.net integration activated!' : 'Failed to activate Visma.net integration.');
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

Route::get('/status-check', [StatusCheckController::class, 'checkStatus']);
