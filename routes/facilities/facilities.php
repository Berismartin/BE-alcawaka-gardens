<?php

use App\Http\Controllers\Api\Facilities\FacilityController;
use Illuminate\Support\Facades\Route;

// Public website
Route::get('facilities', [FacilityController::class, 'publicIndex'])->name('facilities.public');

// Staff portal
Route::prefix('portal/facilities')->name('portal.facilities.')->middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::get('/', [FacilityController::class, 'index'])->name('index');
    Route::post('/', [FacilityController::class, 'store'])->name('store');
    Route::get('/{id}', [FacilityController::class, 'show'])->name('show');
    Route::patch('/{id}', [FacilityController::class, 'update'])->name('update');
    Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
});
