<?php

use App\Http\Controllers\JobMonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('/job-monitor')->group(function() {
    Route::get('/', [JobMonitorController::class, 'dashboard'])->name('jobMonitor.dashboard');
    Route::get('/queue', [JobMonitorController::class, 'queue'])->name('jobMonitor.queue');
    Route::get('/failed-jobs', [JobMonitorController::class, 'failedJobs'])->name('jobMonitor.failedJobs');
    Route::post('/failed-jobs/retry', [JobMonitorController::class, 'retryJobs'])->name('jobMonitor.retryJobs');
});
