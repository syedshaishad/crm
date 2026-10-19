<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0)->after('sign_token');
            $table->timestamp('first_viewed_at')->nullable()->after('view_count');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->string('last_view_ip', 45)->nullable()->after('last_viewed_at');
            $table->timestamp('last_followed_up_at')->nullable()->after('last_view_ip');
        });
        Schema::create('proposal_followup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('channel', 20)->default('email');
            $table->string('recipient', 255)->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('sent');
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_followup_logs');
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['view_count', 'first_viewed_at', 'last_viewed_at', 'last_view_ip', 'last_followed_up_at']);
        });
    }
};
