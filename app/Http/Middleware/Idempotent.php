<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe retries for money and booking mutations. The client sends an
 * Idempotency-Key header (a UUID per user action). The first request runs; a
 * retry with the same key gets the stored response instead of running twice.
 *
 * ->middleware('idempotent')            key optional
 * ->middleware('idempotent:required')   request rejected without a key
 */
class Idempotent
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return $mode === 'required'
                ? $this->error('This request needs an Idempotency-Key header.', 400)
                : $next($request);
        }
        if (strlen($key) > 100) {
            return $this->error('Idempotency-Key must be 100 characters or fewer.', 400);
        }

        // Signed-in staff get their own key space; website guests share one (keys are random UUIDs).
        $scope = $request->user() ? 'user:'.$request->user()->id : 'guest';
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        // "Insert unless taken" never raises, so it is safe inside a Postgres transaction
        // (catching a unique violation there would abort the whole transaction).
        $claimed = IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $request->user()?->id, 'scope' => $scope, 'key' => $key, 'fingerprint' => $fingerprint,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = IdempotencyKey::where('scope', $scope)->where('key', $key)->first();
        if (! $claimed) {
            $existing = $record;

            return match (true) {
                $existing === null => $this->error('Please try again.', 409),
                $existing->fingerprint !== $fingerprint => $this->error('This Idempotency-Key was already used for a different request.', 422),
                $existing->status === null => $this->error('This request is still being processed.', 409),
                default => response($existing->body, $existing->status, ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true']),
            };
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $record->update(['status' => $response->getStatusCode(), 'body' => $response->getContent()]);
        } else {
            $record->delete(); // a failed attempt may be retried with the same key
        }

        return $response;
    }

    private function error(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => []], $status);
    }
}
