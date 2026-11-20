<?php

use App\Http\Controllers\GpsTrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'gps-tracking-demo']));
    Route::get('/sessions/{session}', [GpsTrackingController::class, 'sessionJson']);
    Route::post('/sessions/{session}/pings', [GpsTrackingController::class, 'ingest']);
});
