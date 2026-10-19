<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        // Make uploaded files (logos, favicons, receipts) web-servable by
        // creating the public/storage link — the same as `php artisan
        // storage:link`. Idempotent: skips itself when the link already exists.
        // Failure-tolerant by design: hosts that forbid symlinks still work
        // through the storage.fallback route, and admins get a banner.
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            // Non-fatal — never fail a deployment over the link alone.
        }
    }

    public function down(): void
    {
        // Intentionally empty: the link is shared infrastructure, not something
        // a rollback should tear down.
    }
};
