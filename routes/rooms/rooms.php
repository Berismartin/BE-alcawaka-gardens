<?php

use App\Http\Controllers\Api\Rates\SeasonalDiscountController;
use App\Http\Controllers\Api\Rooms\RoomController;
use App\Http\Controllers\Api\Rooms\RoomTypeController;
use Illuminate\Support\Facades\Route;

// Public website
Route::get('room-types', [RoomTypeController::class, 'publicIndex'])->name('room-types.public');

Route::prefix('portal')->name('portal.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:rooms.view')->group(function () {
        Route::get('room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
        Route::get('room-types/{id}', [RoomTypeController::class, 'show'])->name('room-types.show');
        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('rooms/{id}', [RoomController::class, 'show'])->name('rooms.show');
        Route::get('seasonal-discounts', [SeasonalDiscountController::class, 'index'])->name('seasonal-discounts.index');
    });

    Route::middleware('permission:rooms.manage')->group(function () {
        Route::post('room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
        Route::patch('room-types/{id}', [RoomTypeController::class, 'update'])->name('room-types.update');
        Route::delete('room-types/{id}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');
        Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::patch('rooms/{id}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('rooms/{id}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    Route::middleware('permission:rates.manage')->group(function () {
        Route::post('seasonal-discounts', [SeasonalDiscountController::class, 'store'])->name('seasonal-discounts.store');
        Route::put('seasonal-discounts/{id}', [SeasonalDiscountController::class, 'update'])->name('seasonal-discounts.update');
        Route::delete('seasonal-discounts/{id}', [SeasonalDiscountController::class, 'destroy'])->name('seasonal-discounts.destroy');
    });

    Route::patch('rooms/{id}/status', [RoomController::class, 'changeStatus'])
        ->middleware('permission:housekeeping.manage')->name('rooms.status');
});
