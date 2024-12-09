<?php

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\AppShipmentController;
use App\Http\Controllers\AppWarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('/app')->group(function() {
    Route::prefix('/v1')->group(function() {

        Route::post('/login', [LoginController::class, 'login']);

        Route::prefix('/todo')->group(function() {
            Route::get('/queues', [TodoController::class, 'getQueues']);
            Route::get('/queues/{queue}', [TodoController::class, 'getQueue']);
            Route::get('/queues/{queue}/count', [TodoController::class, 'getQueueCount']);
            Route::get('/queues/{queue}/{item}', [TodoController::class, 'getItem']);
            Route::post('/queues/{queue}/{item}/reserve', [TodoController::class, 'reserveItem']);
            Route::post('/queues/{queue}/{item}/unreserve', [TodoController::class, 'unreserveItem']);
            Route::post('/queues/{queue}/{item}/submit', [TodoController::class, 'submitItem']);

            Route::post('/create-collect-article', [TodoController::class, 'createItemCollectArticle']);
        });

        Route::prefix('/shipments')->group(function() {
            Route::get('/', [AppShipmentController::class, 'list']);
            Route::get('/history', [AppShipmentController::class, 'listHistory']);
            Route::get('/{shipment}', [AppShipmentController::class, 'get']);
            Route::post('/{shipment}/ping', [AppShipmentController::class, 'ping']);
            Route::post('/{shipment}/unping', [AppShipmentController::class, 'unping']);
            Route::post('/{shipment}/pick', [AppShipmentController::class, 'pick']);
            Route::post('/{shipment}/complete', [AppShipmentController::class, 'complete']);
            Route::post('/{shipment}/wgr', [AppShipmentController::class, 'wgr']);
            Route::get('/{shipment}/print', [AppShipmentController::class, 'print']);
            Route::post('/{shipment}/clear-visma', [AppShipmentController::class, 'clearVisma']);
        });

        Route::prefix('/warehouse')->group(function() {
            Route::get('/movements', [AppWarehouseController::class, 'getMovements']);
            Route::get('/movements/{stockItemMovement}', [AppWarehouseController::class, 'getMovement']);
            Route::post('/movements/{stockItemMovement}/ping', [AppWarehouseController::class, 'pingMovement']);
            Route::post('/movements/{stockItemMovement}/unping', [AppWarehouseController::class, 'unpingMovement']);
            Route::post('/movements/{stockItemMovement}/confirm', [AppWarehouseController::class, 'confirmMovement']);
        });
    });
});
