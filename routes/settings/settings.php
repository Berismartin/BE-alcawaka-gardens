<?php

use App\Http\Controllers\Api\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/settings')->name('portal.settings.')->middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::get('/', [SettingsController::class, 'show'])->name('show');
    Route::patch('/', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('update');
});
