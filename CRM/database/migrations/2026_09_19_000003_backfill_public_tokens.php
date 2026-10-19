<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Rows created before the token columns existed have NULL tokens, which
     * hides them from portal links and public pages. Backfill once, keep
     * existing tokens untouched so shared links never break.
     */
    public function up(): void
    {
        foreach (['invoices', 'payments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'public_token')) {
                continue;
            }
            foreach (DB::table($table)->whereNull('public_token')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['public_token' => Str::random(32)]);
            }
        }
    }

    public function down(): void
    {
        // One-way backfill: tokens are opaque, nothing to undo.
    }
};
