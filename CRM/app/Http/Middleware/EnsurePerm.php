<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Granular module gate: perm:{module}. Safe browser verbs (GET/HEAD)
 * need "view", everything else needs "full". Admins always pass;
 * everyone else falls back to their role default (full) unless the
 * admin set an explicit level on their user record.
 */
class EnsurePerm
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $need = $request->isMethodCacheable() ? 'view' : 'full';
        if (! function_exists('user_can') || ! user_can($module, $need)) {
            abort(403, 'You do not have access to this section.');
        }

        return $next($request);
    }
}
