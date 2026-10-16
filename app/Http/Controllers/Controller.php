<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /** Every API response: {"success": true, "data": ..., "message": ...}. */
    protected function respond(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message], $status);
    }

    /**
     * Paginated list: the same envelope plus meta for the table footer.
     *
     * @param  class-string<JsonResource>  $resource
     */
    protected function respondPage(LengthAwarePaginator $page, string $resource, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resource::collection($page->items()),
            'message' => $message,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
