<?php

use App\Http\Controllers\Api\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/media')->name('portal.media.')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::patch('/order', [MediaController::class, 'reorder'])->name('reorder');
    Route::delete('/{id}', [MediaController::class, 'destroy'])->name('destroy');
});

Route::get('media/{id}/file', [MediaController::class, 'file'])->middleware('signed')->name('media.file');
