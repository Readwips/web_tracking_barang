<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentQuickActionController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackingController::class, 'index'])->name('home');
Route::get('/tracking', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/tracking', [TrackingController::class, 'search'])->name('tracking.search');
Route::get('/tracking/{containerNumber}', [TrackingController::class, 'show'])->name('tracking.show');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');

    Route::middleware('role:admin,operator')->group(function () {
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::get('/shipments/{shipment}/edit', [ShipmentController::class, 'edit'])->name('shipments.edit');
        Route::put('/shipments/{shipment}', [ShipmentController::class, 'update'])->name('shipments.update');
        Route::patch('/shipments/{shipment}/quick-action', ShipmentQuickActionController::class)->name('shipments.quick-action');
        Route::delete('/shipments/{shipment}', [ShipmentController::class, 'destroy'])->name('shipments.destroy');

        Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/ai-assistant/customer-notice', [AiAssistantController::class, 'customerNotice'])->name('ai.customer-notice');
        Route::post('/ai-assistant/operational-summary', [AiAssistantController::class, 'operationalSummary'])->name('ai.operational-summary');

        Route::get('/master/{resource}', [MasterDataController::class, 'index'])->name('master.index');
        Route::get('/master/{resource}/create', [MasterDataController::class, 'create'])->name('master.create');
        Route::post('/master/{resource}', [MasterDataController::class, 'store'])->name('master.store');
        Route::get('/master/{resource}/{id}/edit', [MasterDataController::class, 'edit'])->name('master.edit');
        Route::put('/master/{resource}/{id}', [MasterDataController::class, 'update'])->name('master.update');
        Route::delete('/master/{resource}/{id}', [MasterDataController::class, 'destroy'])->name('master.destroy');
    });

    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
});

require __DIR__.'/auth.php';
