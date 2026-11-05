<?php

namespace Modules\Updater;

use Modules\Settings\SettingStore;

class VersionTracker
{
    public static function current(): string
    {
        try {
            return (string) app(SettingStore::class)->get('app.version', config('version.version', '1.0.0'));
        } catch (\Throwable $e) {
            return config('version.version', '1.0.0');
        }
    }

    public static function set(string $version): void
    {
        app(SettingStore::class)->set('app.version', $version, 'system');
    }
}
