<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enforces token scopes on mutating API routes. Tokens carry either ['*']
 * (full access, the default) or ['read'] (GET-only). Session-cookie callers
 * have no token and are unaffected; pre-scope tokens keep full access.
 */
class EnsureTokenWrite
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // Session-cookie callers carry no token and are unaffected; legacy
        // tokens (abilities ['*']) pass tokenCan the same as full-access ones.
        if ($user && $user->currentAccessToken() && ! $user->tokenCan('write')) {
            return response()->json(['message' => 'This token is read-only.'], 403);
        }

        return $next($request);
    }
}
