<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp can be delivered through Meta Cloud API or AiSensy. The log already
 * records the template/campaign and delivery status; this adds the provider so
 * the reminder audit trail can tell the two apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_logs') || Schema::hasColumn('whatsapp_logs', 'provider')) {
            return;
        }

        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->string('provider', 20)->default('meta')->after('template');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_logs') || ! Schema::hasColumn('whatsapp_logs', 'provider')) {
            return;
        }

        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
