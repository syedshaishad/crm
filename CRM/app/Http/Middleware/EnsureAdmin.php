<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Administrators only.');
        }

        return $next($request);
    }
}
