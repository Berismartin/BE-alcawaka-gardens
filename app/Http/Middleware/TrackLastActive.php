<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "Last active" on the staff list (B12). Written at most every 5 minutes per person. */
class TrackLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $response;
    }
}
