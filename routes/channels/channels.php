<?php

use App\Http\Controllers\Api\Channels\ChannelController;
use Illuminate\Support\Facades\Route;

// Public iCal feed that OTAs poll.
Route::get('ical/{token}.ics', [ChannelController::class, 'feed'])->middleware('throttle:60,1')->name('channels.feed');

Route::prefix('portal/channels')->name('portal.channels.')->middleware(['auth:sanctum', 'permission:channels.manage'])->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::get('/availability', [ChannelController::class, 'availability'])->name('availability');
    Route::post('/', [ChannelController::class, 'store'])->name('store');
    Route::post('/sync', [ChannelController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
    Route::put('/{id}', [ChannelController::class, 'update'])->name('update');
    Route::delete('/{id}', [ChannelController::class, 'destroy'])->name('destroy');
});
