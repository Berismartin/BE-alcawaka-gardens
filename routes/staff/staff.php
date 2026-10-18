<?php

use App\Http\Controllers\Api\Staff\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal/staff')->name('portal.staff.')->middleware(['auth:sanctum', 'permission:staff.manage'])->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::post('/', [StaffController::class, 'store'])->middleware('throttle:20,1,staff.store')->name('store');
    Route::patch('/{id}', [StaffController::class, 'update'])->whereNumber('id')->name('update');
    Route::post('/{id}/resend-invite', [StaffController::class, 'resendInvite'])->whereNumber('id')->middleware('throttle:10,1,staff.resend-invite')->name('resend-invite');
});
