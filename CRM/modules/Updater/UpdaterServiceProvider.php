<?php

namespace Modules\Updater;

use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UpdateManager::class);
    }

    public function boot(): void
    {
        // UPDATE_PACKAGE_DIR is commonly written as "storage/app/updates".
        // Anchor a relative value to the app root: under FPM the process CWD is
        // the docroot (public/), so the same string would otherwise mean
        // public/storage/app/updates there and the app root under artisan serve.
        $dir = trim((string) config('version.package_dir', ''));
        if ($dir === '') {
            $dir = storage_path('app/updates');
        } elseif (! preg_match('#^(?:[A-Za-z]:[\\\\/]|[\\\\/]{1,2})#', $dir)) {
            $dir = base_path($dir);
        }
        config(['version.package_dir' => rtrim($dir, '/\\')]);
    }
}
