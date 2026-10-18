<?php

use App\Http\Controllers\Api\Content\ContentController;
use App\Http\Controllers\Api\Content\EnquiryController;
use Illuminate\Support\Facades\Route;

// Public website
Route::get('site', [ContentController::class, 'site'])->name('site');
Route::post('enquiries', [EnquiryController::class, 'store'])->middleware('throttle:5,1,content.enquiries.store')->name('enquiries.store');

// Staff portal: website CMS
Route::prefix('portal/content')->name('portal.content.')->middleware(['auth:sanctum', 'permission:content.manage'])->group(function () {
    Route::get('site', [ContentController::class, 'siteContent'])->name('site.show');
    Route::put('site', [ContentController::class, 'updateSiteContent'])->name('site.update');
    Route::get('/', [ContentController::class, 'index'])->name('index');
    Route::post('/', [ContentController::class, 'store'])->name('store');
    Route::patch('/{id}', [ContentController::class, 'update'])->name('update');
    Route::delete('/{id}', [ContentController::class, 'destroy'])->name('destroy');
});

Route::prefix('portal/enquiries')->name('portal.enquiries.')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [EnquiryController::class, 'index'])->middleware('permission:enquiries.view')->name('index');
    Route::patch('/{id}', [EnquiryController::class, 'update'])->middleware('permission:enquiries.manage')->name('update');
    Route::post('/{id}/apply', [EnquiryController::class, 'apply'])->middleware('permission:reservations.manage')->name('apply');
});
