<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloud Run (und jeder Reverse Proxy) terminiert TLS und leitet per
        // X-Forwarded-* weiter. Vertrauen ist nötig, damit Laravel https-URLs
        // erzeugt (u. a. korrekte OAuth-Redirects).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetLocale::class,
            EnsureEmailIsVerified::class,
            EnsureProfileComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
