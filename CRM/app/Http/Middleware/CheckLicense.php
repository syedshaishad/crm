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
        // Auth + installer + license plumbing always stays reachable: a
        // blocked install must still be able to log in and activate.
        // Everything else — app AND client portal — needs a valid license.
        if ($name === '' || $name === 'login' || $name === 'logout'
            || str_starts_with($name, 'password.') || $name === 'portal.login'
            || str_starts_with($name, 'installer.') || $name === 'up') {
            return $next($request);
        }
        if (! $lm->accessAllowed()) {
            // Guests go to login first (web only): nothing behind it works
            // without a license anyway. Portal/API/JSON keep their own flows.
            if (! auth()->check() && ! str_starts_with($name, 'portal.') && ! $request->expectsJson() && ! $request->is('api/*')) {
                return redirect()->guest(route('login'));
            }
            // Never brick the admin: dashboard + all settings (license entry,
            // updates, user cleanup) stay reachable so an expired install can
            // be fixed. Everything else 402s.
            if ($name === 'dashboard' || $name === 'logout' || str_starts_with($name, 'settings.')) {
                return $next($request);
            }

            return response()->view('licensing.blocked', ['license' => $lm->status()], 402);
        }
        // Module gate: licensed-out areas 402 even by direct URL (nav hides
        // them too). Settings/API/license plumbing itself always stays open.
        $module = $lm->moduleForRoute($name ?: ltrim($request->path(), '/'));
        if ($module && ! $lm->moduleAllowed($module)) {
            return response()->view('licensing.blocked', ['license' => $lm->status() + ['reason' => 'This install is not licensed for '.$module.'.']], 402);
        }

        return $next($request);
    }
}
