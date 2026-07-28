<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Resolves the signed `kiosk_device` cookie to an enrolled tablet.
            'kiosk' => App\Http\Middleware\EnsureKioskDevice::class,
            // Server-side half of the 2-minute kiosk idle drop. The Alpine
            // `idleRelease` component is a convenience; THIS is authoritative.
            'kiosk.idle' => App\Http\Middleware\EnforceKioskIdleTimeout::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
