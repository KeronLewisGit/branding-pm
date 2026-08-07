<?php

use App\Http\Middleware\EnforceKioskIdleTimeout;
use App\Http\Middleware\EnsureKioskDevice;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Response security headers on every web response (OWASP Secure
        // Headers). Appended, so it wraps everything the group produces.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            // Resolves the signed `kiosk_device` cookie to an enrolled tablet.
            'kiosk' => EnsureKioskDevice::class,
            // Server-side half of the 2-minute kiosk idle drop. The Alpine
            // `idleRelease` component is a convenience; THIS is authoritative.
            'kiosk.idle' => EnforceKioskIdleTimeout::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
