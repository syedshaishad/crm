<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Licensing\LicenseManager;

/**
 * Config-toggle gate: derives the module from the route name (api.* prefix
 * stripped) and 404s when switched off. Unmapped routes (dashboard, settings,
 * search shell, portal, public links) always pass. License-level gating lives
 * in CheckLicense; both must allow.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $name = $route ? (string) $route->getName() : '';
        if ($name !== '') {
            try {
                $lm = app(LicenseManager::class);
                $base = preg_replace('/^api\./', '', $name);
                $module = $lm->moduleForRoute($base) ?? $lm->moduleForRoute(ltrim($request->path(), '/'));
                if ($module && ! module_enabled($module)) {
                    abort(404);
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Fail open on helper errors — licensing still enforces paid gates.
            }
        }

        return $next($request);
    }
}
