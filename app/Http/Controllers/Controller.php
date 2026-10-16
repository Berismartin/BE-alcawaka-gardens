<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /** Every API response: {"success": true, "data": ..., "message": ...}. */
    protected function respond(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }
}
