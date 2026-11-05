<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Poor-man's cron for shared hosting without cron access: at most once per
 * 15 minutes, a web hit spawns `php artisan schedule:run` in the background
 * and continues immediately (the request never waits for backups/reminders).
 * VPS owners should still set a real cron `* * * * * php artisan schedule:run`
 * — the 15-minute gate makes double-firing harmless either way.
 */
class SchedulerHeartbeat
{
    protected bool $due = false;

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        try {
            $last = (int) Cache::get('scheduler.last_ping', 0);
            if (time() - $last >= 900) {
                Cache::put('scheduler.last_ping', time(), 3600);
                $this->due = true;
            }
        } catch (\Throwable $e) {
        }

        return $response;
    }

    /** Run after the response is sent so the visitor never waits for cron. */
    public function terminate(Request $request, $response): void
    {
        if ($this->due) {
            $this->spawn();
        }
    }

    protected function spawn(): void
    {
        $artisan = base_path('artisan');
        $php = PHP_BINARY ?: 'php';
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start "" /B "'.str_replace('"', '', $php).'" "'.$artisan.'" schedule:run > NUL 2>&1';
            @pclose(@popen($cmd, 'r'));
        } else {
            @exec(escapeshellarg($php).' '.escapeshellarg($artisan).' schedule:run > /dev/null 2>&1 &');
        }
    }
}
