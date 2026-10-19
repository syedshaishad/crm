<?php

namespace Modules\Updater;

use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UpdateManager::class);
    }

    public function boot(): void {}
}
