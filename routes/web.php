<?php

use App\Http\Controllers\GpsTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GpsTrackingController::class, 'map'])->name('map.index');
Route::post('/simulate', [GpsTrackingController::class, 'simulateNew'])->name('map.simulate');
