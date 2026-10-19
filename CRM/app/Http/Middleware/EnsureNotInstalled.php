<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (file_exists(config('installer.lock_file')) || config('app.installed')) {
            abort(403, 'Application is already installed. Delete storage/framework/installed.lock only to reinstall.');
        }

        return $next($request);
    }
}
