<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('vendor');
            $table->string('reference', 100)->nullable()->after('method');
            $table->unsignedBigInteger('created_by')->nullable()->index()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['method', 'reference', 'created_by']);
        });
    }
};
