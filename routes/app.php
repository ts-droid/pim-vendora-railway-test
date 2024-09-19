<?php

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\TodoController;
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
        });

    });
});
