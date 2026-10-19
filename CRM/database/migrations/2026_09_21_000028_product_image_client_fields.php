<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('hsn_code');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
