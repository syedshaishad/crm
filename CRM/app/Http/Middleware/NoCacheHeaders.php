<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated pages must never be cached by the browser (or its
 * back-forward cache), otherwise users keep interacting with stale
 * HTML/JS after updates. Safe to apply globally: static assets served
 * from public/ bypass Laravel and keep their own caching.
 */
class NoCacheHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            // Clickjacking / MIME-sniffing / referrer hardening. No iframes or
            // plugins are used anywhere (print uses window.print, not frames).
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }

        return $response;
    }
}
