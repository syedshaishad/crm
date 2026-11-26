<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Licensing\LicenseManager;

class CheckLicense
{
    public function handle(Request $request, Closure $next)
    {
        /** @var LicenseManager $lm */
        $lm = app(LicenseManager::class);
        $route = $request->route();
        $name = $route ? (string) $route->getName() : '';
        // Unnamed routes used to skip the gate entirely. Deny by default:
        // fall back to the path and only allow the explicit plumbing below.
        $gate = $name !== '' ? $name : ltrim($request->path(), '/');
        // Auth + installer + license plumbing always stays reachable: a
        // blocked install must still be able to log in and activate.
        // Everything else — app AND client portal — needs a valid license.
        if ($gate === 'login' || $gate === 'logout' || $gate === 'up'
            || str_starts_with($gate, 'password.') || $gate === 'portal.login'
            || str_starts_with($gate, 'installer.')
            || str_starts_with($gate, 'storage/')) {
            return $next($request);
        }
        if (! $lm->accessAllowed()) {
            // Guests go to login first (web only): nothing behind it works
            // without a license anyway. Portal/API/JSON keep their own flows.
            if (! auth()->check() && ! str_starts_with($gate, 'portal.') && ! $request->expectsJson() && ! $request->is('api/*')) {
                return redirect()->guest(route('login'));
            }
            // Never brick the admin: dashboard + all settings (license entry,
            // updates, user cleanup) stay reachable so an expired install can
            // be fixed. Everything else 402s.
            if ($gate === 'dashboard' || str_starts_with($gate, 'settings.') || str_starts_with($gate, 'settings/')) {
                return $next($request);
            }

            return response()->view('licensing.blocked', ['license' => $lm->status()], 402);
        }
        // Module gate: licensed-out areas 402 even by direct URL (nav hides
        // them too). Settings/API/license plumbing itself always stays open.
        $module = $lm->moduleForRoute($gate);
        if ($module && ! $lm->moduleAllowed($module)) {
            return response()->view('licensing.blocked', ['license' => $lm->status() + ['reason' => 'This install is not licensed for '.$module.'.']], 402);
        }

        return $next($request);
    }
}
