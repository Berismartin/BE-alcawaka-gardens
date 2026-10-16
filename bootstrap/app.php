<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\Idempotent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['staff' => EnsureStaff::class, 'permission' => EnsurePermission::class, 'idempotent' => Idempotent::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApi): void {
        $exceptions->shouldRenderJsonWhen($isApi);

        // Every API error uses the same envelope as success: {"success": false, "message", "errors"}.
        $exceptions->render(fn (DomainException $e) => response()->json(
            ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], $e->status()
        ));
        $exceptions->render(fn (ValidationException $e, Request $r) => $isApi($r) ? response()->json(
            ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422
        ) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $isApi($r) ? response()->json(
            ['success' => false, 'message' => 'Unauthenticated.', 'errors' => []], 401
        ) : null);
        $exceptions->render(fn (AccessDeniedHttpException|AuthorizationException $e, Request $r) => $isApi($r) ? response()->json(
            ['success' => false, 'message' => "You don't have permission to do this.", 'errors' => []], 403
        ) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => $isApi($r) ? response()->json(
            ['success' => false, 'message' => 'Not found.', 'errors' => []], 404
        ) : null);
    })->create();
