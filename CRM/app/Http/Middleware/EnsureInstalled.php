<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (! file_exists(config('installer.lock_file')) && ! config('app.installed')) {
            return redirect()->route('installer.welcome');
        }

        return $next($request);
    }
}
