<?php

use App\Http\Controllers\Api\Guests\GuestController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/guests')->name('portal.guests.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:guests.view')->group(function () {
        Route::get('/', [GuestController::class, 'index'])->name('index');
        Route::get('/{id}', [GuestController::class, 'show'])->name('show');
    });
    Route::middleware('permission:guests.manage')->group(function () {
        Route::post('/', [GuestController::class, 'store'])->name('store');
        Route::patch('/{id}', [GuestController::class, 'update'])->name('update');
        Route::delete('/{id}', [GuestController::class, 'destroy'])->name('destroy');
    });
});
