<?php

use App\Http\Middleware\DevAutoLogin;
use App\Http\Middleware\EnsureTestingAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TouchSitePresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            DevAutoLogin::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TouchSitePresence::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'analytics/funnel-event',
        ]);

        $middleware->alias([
            'testing.admin' => EnsureTestingAdmin::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
