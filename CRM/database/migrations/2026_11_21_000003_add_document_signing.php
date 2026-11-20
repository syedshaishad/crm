<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->string('sign_token', 64)->nullable()->unique();
            $t->string('signed_by', 255)->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->string('signature_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropColumn(['sign_token', 'signed_by', 'signed_at', 'signature_path']);
        });
    }
};
