<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // ← AÑADE ESTO
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api', // ← Opcional
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthTokenMiddleware::class,
        ]);

        // ← AQUÍ SÍ FUNCIONA
        $middleware->api(prepend: [
            \App\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ], append: [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();