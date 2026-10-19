<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Portal gate: the login must be active AND its client must still exist.
 * Anything else is logged out back to the portal sign-in with a reason.
 */
class EnsureClientActive
{
    public function handle(Request $request, Closure $next)
    {
        $u = Auth::guard('client')->user();
        if (! $u || ! $u->is_active || ! $u->client) {
            Auth::guard('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors(['email' => 'This portal access is no longer active.']);
        }

        return $next($request);
    }
}
