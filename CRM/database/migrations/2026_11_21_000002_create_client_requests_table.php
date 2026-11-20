<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('client_user_id')->nullable();
            $t->string('subject', 180);
            $t->text('body');
            $t->string('status', 20)->default('open');
            $t->string('priority', 20)->default('normal');
            $t->unsignedBigInteger('assigned_to')->nullable();
            $t->json('messages');
            $t->timestamps();
            $t->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_requests');
    }
};
