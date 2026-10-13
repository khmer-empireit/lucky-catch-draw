<?php

use App\Http\Controllers\DrawController;
use App\Http\Controllers\ImportTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/imports/template', ImportTemplateController::class)->name('imports.template');

    Route::get('/draw/{campaign}', [DrawController::class, 'show'])->name('draw.show');
    Route::post('/draw/{campaign}', [DrawController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('draw.store');
    Route::get('/draw/{campaign}/latest', [DrawController::class, 'latest'])->name('draw.latest');

    Route::get('/draws/{draw}/print', [DrawController::class, 'print'])->name('draws.print');
});
