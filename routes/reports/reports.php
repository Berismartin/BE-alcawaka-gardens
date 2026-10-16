<?php

use App\Http\Controllers\Api\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [ReportController::class, 'dashboard'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('reports', [ReportController::class, 'show'])->middleware('permission:reports.view')->name('reports.show');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');
});
