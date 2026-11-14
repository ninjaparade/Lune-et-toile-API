<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Token-authenticated channel auth for the app: POST /api/v1/broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only: every error renders as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
