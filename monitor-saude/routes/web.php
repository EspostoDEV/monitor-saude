<?php

use App\Http\Controllers\EpidemicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EpidemicController::class, 'index'])->name('dashboard');
Route::get('/api/history/{cityId}', [EpidemicController::class, 'history']);