<?php

use App\Http\Controllers\Api\Reservations\FolioController;
use App\Http\Controllers\Api\Reservations\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:reservations.view')->group(function () {
        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{id}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::get('calendar', [ReservationController::class, 'calendar'])->name('calendar');
        Route::get('availability', [ReservationController::class, 'availability'])->name('availability');
    });

    Route::prefix('reservations')->name('reservations.')->middleware('permission:reservations.manage')->group(function () {
        Route::post('/', [ReservationController::class, 'store'])->middleware('idempotent:required')->name('store');
        Route::patch('/{id}', [ReservationController::class, 'update'])->name('update');
        Route::post('/{id}/check-in', [ReservationController::class, 'checkIn'])->name('check-in');
        Route::post('/{id}/check-out', [ReservationController::class, 'checkOut'])->middleware('idempotent:required')->name('check-out');
        Route::post('/{id}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/no-show', [ReservationController::class, 'noShow'])->name('no-show');
        Route::post('/{id}/charges', [FolioController::class, 'addCharge'])->name('charges.store');
        Route::delete('/{id}/charges/{chargeId}', [FolioController::class, 'removeCharge'])->name('charges.destroy');
        Route::post('/{id}/receipt', [FolioController::class, 'sendReceipt'])->middleware('throttle:10,1')->name('receipt');
    });

    Route::post('reservations/{id}/payments', [FolioController::class, 'recordPayment'])
        ->middleware(['permission:payments.record', 'idempotent:required'])->name('reservations.payments.store');
});
