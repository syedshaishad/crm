<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('recipient', 30)->nullable();
            $table->string('template', 100)->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('sent');
            $table->string('wamid', 255)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('last_whatsapp_at')->nullable()->after('last_nudged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('last_whatsapp_at');
        });
    }
};
