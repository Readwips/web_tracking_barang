<?php

use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/shipments/{tracking_number}', [ShipmentController::class, 'show']);

    Route::middleware(['auth.basic', 'role:admin,operator'])->group(function () {
        Route::get('/shipments', [ShipmentController::class, 'index']);
        Route::post('/shipments', [ShipmentController::class, 'store']);
        Route::put('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
    });
});
