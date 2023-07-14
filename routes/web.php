<?php

use Illuminate\Support\Facades\Route;

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
    abort(404);
});

Route::prefix('/visma')->group(function() {
    Route::any('/activate', function() {
        $vismaController = new \App\Http\Controllers\VismaNetController();

        return redirect($vismaController->getAuthURL());
    })->name('visma.activate');

    Route::any('/callback', function(\Illuminate\Http\Request $request) {
        $vismaController = new \App\Http\Controllers\VismaNetController();

        $activated = $vismaController->authCallback($request);

        die($activated ? 'Visma.net integration activated!' : 'Failed to activate Visma.net integration.');
    })->name('visma.callback');
});
