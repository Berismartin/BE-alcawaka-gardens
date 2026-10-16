<?php

use App\Http\Controllers\Api\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('portal/audit-logs', [AuditLogController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:audit.view'])
    ->name('portal.audit-logs.index');
