<?php

use App\Http\Controllers\Api\Pos\PosController;
use Illuminate\Support\Facades\Route;

// On the terminal itself (no staff token yet).
Route::prefix('pos')->name('pos.')->middleware('pos.terminal')->group(function () {
    Route::get('staff', [PosController::class, 'staff'])->middleware('throttle:60,1,pos.staff')->name('staff');
    Route::post('login', [PosController::class, 'login'])->middleware('throttle:20,1,pos.login')->name('login');
});

Route::patch('auth/me/pin', [PosController::class, 'setPin'])->middleware(['auth:sanctum', 'staff', 'throttle:6,1,pos.auth.pin'])->name('auth.pin');

Route::prefix('portal/pos')->name('portal.pos.')->middleware(['auth:sanctum', 'permission:pos.manage'])->group(function () {
    Route::get('terminals', [PosController::class, 'terminals'])->name('terminals.index');
    Route::post('terminals', [PosController::class, 'register'])->name('terminals.store');
    Route::delete('terminals/{id}', [PosController::class, 'revoke'])->name('terminals.destroy');
    Route::delete('staff/{id}/pin', [PosController::class, 'clearPin'])->whereNumber('id')->name('pin.clear');
});
