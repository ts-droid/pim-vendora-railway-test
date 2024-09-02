<?php

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\TodoController;
use Illuminate\Support\Facades\Route;

Route::prefix('/app')->group(function() {
    Route::prefix('/v1')->group(function() {

        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/create-user', [LoginController::class, 'createUser'])->middleware('api.key');

        Route::middleware('authToken')->group(function() {
            Route::prefix('/todo')->group(function() {
                Route::get('/queues', [TodoController::class, 'getQueues']);
                Route::get('/queues/{queue}', [TodoController::class, 'getQueue']);
                Route::get('/queues/{queue}/count', [TodoController::class, 'getQueueCount']);
                Route::post('/queues/{queue}/reserve', [TodoController::class, 'reserveQueue']);
            });
        });

    });
});
