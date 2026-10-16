<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff portal routes: any signed-in user whose role is not guest. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->role->isStaff()) {
            return response()->json(['success' => false, 'message' => 'Staff access only.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
