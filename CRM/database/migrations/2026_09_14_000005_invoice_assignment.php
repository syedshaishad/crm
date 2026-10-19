<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Plain indexed columns (no FK constraint) for MySQL/SQLite portability.
            $table->unsignedBigInteger('assigned_to')->nullable()->index()->after('client_id');
            $table->unsignedBigInteger('created_by')->nullable()->index()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['assigned_to', 'created_by']);
        });
    }
};
