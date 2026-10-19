<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('accept_mode', 20)->nullable()->after('signed_by');
            $table->string('accept_note', 255)->nullable()->after('accept_mode');
            $table->string('close_reason', 255)->nullable()->after('accept_note');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['accept_mode', 'accept_note', 'close_reason']);
        });
    }
};
