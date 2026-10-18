<?php

use App\Http\Controllers\Api\Booking\OnlineBookingController;
use App\Http\Controllers\Api\Booking\OnlineFacilityBookingController;
use Illuminate\Support\Facades\Route;

// Public website booking: no account, the booking key proves ownership.
Route::prefix('booking')->name('booking.')->group(function () {
    Route::get('search', [OnlineBookingController::class, 'search'])->middleware('throttle:60,1,online.search')->name('search');
    Route::post('/', [OnlineBookingController::class, 'store'])->middleware(['throttle:10,1,online.store', 'idempotent:required'])->name('store');
    Route::post('lookup', [OnlineBookingController::class, 'lookup'])->middleware('throttle:10,1,online.lookup')->name('lookup');
    Route::get('{reference}', [OnlineBookingController::class, 'show'])->middleware('throttle:120,1,online.show')->name('show');
    Route::get('{reference}/receipt', [OnlineBookingController::class, 'receipt'])->middleware('throttle:20,1,online.receipt')->name('receipt');
    Route::post('{reference}/pay', [OnlineBookingController::class, 'pay'])->middleware('throttle:10,1,online.pay')->name('pay');
    Route::post('{reference}/simulate', [OnlineBookingController::class, 'simulate'])->middleware('throttle:20,1,online.simulate')->name('simulate');
    Route::post('{reference}/request', [OnlineBookingController::class, 'request'])->middleware('throttle:5,1,online.request')->name('request');
});

// Facility day passes and event space (A08), paid online the same way.
Route::prefix('facility-bookings')->name('facility-bookings.')->group(function () {
    Route::post('/', [OnlineFacilityBookingController::class, 'store'])->middleware(['throttle:10,1,online.facility.store', 'idempotent:required'])->name('store');
    Route::get('{id}', [OnlineFacilityBookingController::class, 'show'])->middleware('throttle:120,1,online.facility.show')->name('show');
    Route::post('{id}/pay', [OnlineFacilityBookingController::class, 'pay'])->middleware('throttle:10,1,online.facility.pay')->name('pay');
    Route::post('{id}/simulate', [OnlineFacilityBookingController::class, 'simulate'])->middleware('throttle:20,1,online.facility.simulate')->name('simulate');
});
