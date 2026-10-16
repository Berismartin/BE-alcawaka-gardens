<?php

use Illuminate\Support\Facades\Route;

// Module routes live in routes/<module>/*.php and are loaded by DomainServiceProvider.
Route::get('health', fn () => ['success' => true, 'data' => ['status' => 'ok'], 'message' => null]);
