<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: ->middleware('permission:facilities.manage'). */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user()?->role->can($ability)) {
            return response()->json(
                ['success' => false, 'message' => "You don't have permission to do this.", 'errors' => []],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
