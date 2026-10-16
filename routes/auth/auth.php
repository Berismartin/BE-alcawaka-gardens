<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');
    Route::post('accept-invite', [AuthController::class, 'acceptInvite'])->middleware('throttle:6,1')->name('accept-invite');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1')->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::patch('me/preferences', [AuthController::class, 'updatePreferences'])->name('preferences');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
