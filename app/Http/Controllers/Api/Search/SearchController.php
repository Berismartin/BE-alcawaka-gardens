<?php

namespace App\Http\Controllers\Api\Search;

use App\Domains\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Read-only lookup with no business rules, so it calls the service directly. */
    public function index(Request $request, SearchService $search): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']])['q'];

        return $this->respond($search->search($request->user(), $q));
    }
}
