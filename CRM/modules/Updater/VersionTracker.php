<?php

namespace Modules\Updater;

use Illuminate\Support\Facades\DB;

class VersionTracker
{
    public static function current(): string
    {
        try {
            $v = DB::table('settings')->where('key', 'app.version')->first();
            if ($v) {
                return $v->value;
            }
        } catch (\Throwable $e) {
        }

        return config('version.version', '1.0.0');
    }

    public static function set(string $version): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'app.version'], ['value' => $version, 'group' => 'system', 'updated_at' => now(), 'created_at' => now()]);
    }
}
