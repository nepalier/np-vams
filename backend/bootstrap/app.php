<?php

use App\Http\Middleware\EnsureIsClientPortalUser;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'client.portal' => EnsureIsClientPortalUser::class,
        ]);

       

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Centralized exception handling (Step 1 NFR): JSON API responses
        // get a consistent {data, meta, errors} envelope, never a raw
        // Laravel HTML error page.
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json([
                'errors' => [[
                    'status' => (string) $status,
                    'title' => class_basename($e),
                    'detail' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'An unexpected error occurred.',
                ]],
            ], $status);
        });
    })->create();
