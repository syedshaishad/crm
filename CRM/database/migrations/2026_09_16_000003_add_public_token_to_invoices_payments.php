<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: an earlier partial run may have added the columns already.
        if (! Schema::hasColumn('invoices', 'public_token')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->string('public_token', 64)->nullable()->unique();
            });
        }
        if (! Schema::hasColumn('payments', 'public_token')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->string('public_token', 64)->nullable()->unique();
            });
        }

        // Backfill existing rows so every invoice/receipt already has a link.
        // Uses the query builder rather than the Eloquent models on purpose:
        // both models use SoftDeletes, which would append a "deleted_at is
        // null" scope, but those columns are not created until the next
        // migration in the chain.
        foreach (['invoices', 'payments'] as $table) {
            DB::table($table)->whereNull('public_token')->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['public_token' => Str::random(32)]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'public_token')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->dropColumn('public_token');
            });
        }
        if (Schema::hasColumn('payments', 'public_token')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->dropColumn('public_token');
            });
        }
    }
};
