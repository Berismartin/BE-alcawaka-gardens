<?php

namespace App\Http\Middleware;

use App\Domains\Pos\Terminal\Services\PosService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** POS routes: only from a registered terminal (X-Terminal-Key header). */
class EnsurePosTerminal
{
    public function __construct(private PosService $pos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $terminal = $this->pos->resolveTerminal($request->header('X-Terminal-Key'));
        if (! $terminal) {
            return response()->json(['success' => false, 'message' => 'This device is not a registered POS terminal.', 'errors' => []], Response::HTTP_FORBIDDEN);
        }
        $request->attributes->set('pos_terminal', $terminal);

        return $next($request);
    }
}
