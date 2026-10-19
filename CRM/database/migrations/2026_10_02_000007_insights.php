<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('type', 20)->default('sales');
            $t->string('frequency', 20)->default('weekly');
            $t->text('recipients');
            $t->string('channel', 20)->default('email');
            $t->boolean('is_active')->default(true);
            $t->date('last_run_on')->nullable();
            $t->timestamps();
        });
        Schema::create('webhooks', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('url', 500);
            $t->string('secret', 64)->nullable();
            $t->text('events');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
            $t->string('event', 60);
            $t->string('status', 20)->default('sent');
            $t->unsignedSmallInteger('http_code')->nullable();
            $t->string('error', 500)->nullable();
            $t->timestamps();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('report_schedules');
    }
};
