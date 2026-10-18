<?php

use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\Search\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->middleware(['auth:sanctum', 'staff'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('search', [SearchController::class, 'index'])->middleware('throttle:60,1,notifications.search')->name('search');
});
