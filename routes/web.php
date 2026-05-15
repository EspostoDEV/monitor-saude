<?php

use App\Http\Controllers\Admin\SyncStatusController;
use App\Http\Controllers\EpidemicController;
use App\Http\Controllers\GeoSpatialController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EpidemicController::class, 'index'])->name('dashboard');
Route::get('/api/history/{cityId}', [EpidemicController::class, 'history']);

Route::get('/api/geo/map', [GeoSpatialController::class, 'index']);

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/sync-status', [SyncStatusController::class, 'index'])->name('sync.status');
    Route::post('/sync-trigger', [SyncStatusController::class, 'sync'])->name('sync.trigger');
    Route::get('/sync-logs', [SyncStatusController::class, 'logs'])->name('sync.logs');
});
