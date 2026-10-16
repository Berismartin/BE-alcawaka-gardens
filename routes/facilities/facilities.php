<?php

use App\Http\Controllers\Api\Facilities\FacilityBookingController;
use App\Http\Controllers\Api\Facilities\FacilityController;
use Illuminate\Support\Facades\Route;

// Public website
Route::get('facilities', [FacilityController::class, 'publicIndex'])->name('facilities.public');

// Staff portal
Route::prefix('portal/facilities')->name('portal.facilities.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:facilities.view')->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('index');
        Route::get('/{id}', [FacilityController::class, 'show'])->name('show');
    });
    Route::middleware('permission:facilities.manage')->group(function () {
        Route::post('/', [FacilityController::class, 'store'])->name('store');
        Route::patch('/{id}', [FacilityController::class, 'update'])->name('update');
        Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
    });
});

Route::prefix('portal/facility-bookings')->name('portal.facility-bookings.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:facilities.view')->group(function () {
        Route::get('/', [FacilityBookingController::class, 'index'])->name('index');
        Route::get('/summary', [FacilityBookingController::class, 'summary'])->name('summary');
    });
    Route::middleware('permission:facilities.book')->group(function () {
        Route::post('/', [FacilityBookingController::class, 'store'])->middleware('idempotent:required')->name('store');
        Route::post('/{id}/pay', [FacilityBookingController::class, 'pay'])->middleware(['permission:payments.record', 'idempotent:required'])->name('pay');
        Route::post('/{id}/cancel', [FacilityBookingController::class, 'cancel'])->name('cancel');
    });
});
