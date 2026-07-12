<?php

use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::get('/shipments', [ShipmentController::class, 'index']);
Route::get('/shipments/{tracking_number}', [ShipmentController::class, 'show']);
Route::post('/shipments', [ShipmentController::class, 'store']);
Route::put('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
