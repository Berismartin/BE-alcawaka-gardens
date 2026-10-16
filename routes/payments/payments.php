<?php

use App\Http\Controllers\Api\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/payments')->name('portal.payments.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/summary', [PaymentController::class, 'summary'])->name('summary');
        Route::get('/export', [PaymentController::class, 'export'])->name('export');
    });
    Route::patch('/{id}/settle', [PaymentController::class, 'settle'])->middleware('permission:payments.record')->name('settle');
    Route::post('/{id}/refund', [PaymentController::class, 'refund'])->middleware(['permission:payments.refund', 'idempotent:required'])->name('refund');
});
