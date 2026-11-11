<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API-only app: never redirect guests to a login page — respond 401 JSON instead.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/api/documentation');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always answer API routes with JSON, even without an Accept header (mobile clients).
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException ? 'Record not found.' : 'Endpoint not found.';

                return response()->json(['message' => $message], 404);
            }
        });
    })->create();
