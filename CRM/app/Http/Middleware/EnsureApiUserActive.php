<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Sanctum authenticates the token but never checks is_active — a deactivated
 * staffer's token would keep working forever. Block it with a JSON 401.
 * (Soft-deleted users already 401 via the user provider returning null.)
 */
class EnsureApiUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! (bool) $user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 401);
        }

        return $next($request);
    }
}
