<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers (OWASP Secure Headers; ASVS v4 §14.4).
 *
 * The application had none. Laravel ships CSRF, cookie encryption and output
 * escaping, but nothing that tells the browser to refuse a framed login page,
 * stop sniffing content types, or leak a referrer to another host — those are
 * headers, and headers have to be sent.
 *
 * Applied to the whole `web` group in bootstrap/app.php.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Clickjacking. A kiosk or an approval screen framed by another
            // site is only ever an attack.
            'X-Frame-Options' => 'DENY',

            // Stop MIME sniffing — an uploaded "photo" that is really HTML
            // must not be executed as HTML.
            'X-Content-Type-Options' => 'nosniff',

            // Send the full URL within our own origin, and only the bare
            // origin outward, so run ids and machine codes do not leak.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // No camera, microphone or location is used outside the run
            // form's file input, which needs none of these APIs.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), interest-cohort=()',
        ];

        foreach ($headers as $name => $value) {
            // Never clobber a header a controller set deliberately.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        /*
         * Content-Security-Policy.
         *
         * `unsafe-inline` and `unsafe-eval` for scripts are, regrettably,
         * load-bearing: Alpine evaluates the x-* attribute expressions this
         * UI is built from, and Livewire injects an inline bootstrap script.
         * Removing them means removing Alpine, so the policy is honest about
         * what it does and does not buy.
         *
         * What it still buys is real: no script, frame, object or form
         * target from another origin, and no plugin content at all. The plant
         * may be offline, so nothing legitimately loads from a CDN.
         */
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                // data: for the signature pad's PNG and inline QR SVGs.
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "object-src 'none'",
            ]));
        }

        /*
         * HSTS, but only over HTTPS and only in production. Sending it from a
         * plain-HTTP dev box would pin the browser to a scheme that host does
         * not serve; sending it from the LAN-IP pilot would strand the
         * tablets. No `preload` — that is a decision for whoever owns the
         * domain, not a default.
         */
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
