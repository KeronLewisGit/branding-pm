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
        /*
         * Trust X-Forwarded-* only from a proxy on a private network.
         *
         * Two things depend on this. The audit log records `request()->ip()`
         * on every state change — without it, a reverse proxy or Docker's
         * published-port NAT means every entry says "the proxy" and the
         * requirement to log an actor's IP is met in name only. And
         * `$request->secure()` is what decides whether HSTS is sent; behind a
         * TLS terminator the connection to PHP is plain HTTP, so without this
         * the header never goes out.
         *
         * Private ranges rather than `*`: trusting every source would let
         * anyone who can reach the app directly forge their own address in
         * the audit trail by sending the header themselves.
         */
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1/32',
            '::1/128',
        ]);

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
