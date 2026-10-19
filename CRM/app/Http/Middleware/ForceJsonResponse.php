<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API clients that forget `Accept: application/json` would otherwise get
 * HTML (or a 302 to /login on auth failure). Normalise it so every API
 * response — including errors — is JSON.
 *
 * Registered GLOBALLY (not on the route) because Laravel runs `auth` before
 * route middleware: by the time a route-level version ran, the 302 would
 * already be decided. The `api/*` guard keeps web routes untouched.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*') && ! $request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
