<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Plain indexed column (no FK constraint) so it works on both
            // MySQL/MariaDB and SQLite without driver-specific issues.
            $table->unsignedBigInteger('project_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
