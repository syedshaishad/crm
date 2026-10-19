<?php

namespace Modules\Installer;

use Illuminate\Support\ServiceProvider;

class InstallerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RequirementsChecker::class);
        $this->app->singleton(EnvWriter::class);
    }

    public function boot(): void {}
}
