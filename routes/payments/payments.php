<?php

use App\Http\Controllers\Api\Payments\OnlinePaymentController;
use App\Http\Controllers\Api\Payments\PaymentController;
use App\Http\Controllers\Api\Payments\PesapalController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/payments')->name('portal.payments.')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/summary', [PaymentController::class, 'summary'])->name('summary');
        Route::get('/export', [PaymentController::class, 'export'])->name('export');
    });
    Route::patch('/{id}/settle', [PaymentController::class, 'settle'])->middleware('permission:payments.record')->name('settle');
    Route::post('/{id}/check', [PaymentController::class, 'check'])->middleware(['permission:payments.record', 'throttle:30,1'])->name('check');
    Route::post('/{id}/refund', [PaymentController::class, 'refund'])->middleware(['permission:payments.refund', 'idempotent:required'])->name('refund');
});

// Pesapal (public): its IPN (registered as GET; POST accepted too) and the guest's return from the payment page.
Route::match(['get', 'post'], 'payments/pesapal/ipn', [PesapalController::class, 'ipn'])->middleware('throttle:120,1')->name('payments.pesapal.ipn');
Route::post('payments/return', [PesapalController::class, 'return'])->middleware('throttle:30,1')->name('payments.return');

// Staff: take a Pesapal payment now (desk or POS), or email an invoice with a pay-now link.
Route::middleware(['auth:sanctum', 'permission:payments.record'])->name('portal.')->group(function () {
    foreach (['reservations' => 'reservation', 'facility-bookings' => 'facility_booking'] as $path => $type) {
        Route::post("portal/{$path}/{id}/online-payments", [OnlinePaymentController::class, 'request'])
            ->defaults('ownerType', $type)->middleware(['idempotent:required', 'throttle:20,1'])->name("{$path}.online-payments");
        Route::post("portal/{$path}/{id}/payment-links", [OnlinePaymentController::class, 'sendLink'])
            ->defaults('ownerType', $type)->middleware('throttle:20,1')->name("{$path}.payment-links");
    }
});

// Public: the guest's invoice link. The token is the secret.
Route::get('pay/{token}', [OnlinePaymentController::class, 'showLink'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{20,64}')->name('pay.show');
Route::post('pay/{token}/checkout', [OnlinePaymentController::class, 'payLink'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{20,64}')->name('pay.checkout');
