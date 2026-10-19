<?php

namespace Modules\Core;

use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // No tenant scoping: single-tenant by design. Nothing shared, nothing central.
    }
}
