<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('twofa_secret', 64)->nullable();
            $t->boolean('twofa_enabled')->default(false);
            $t->text('twofa_recovery_codes')->nullable();
        });

        Schema::create('recurring_expense_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category', 100)->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('vendor', 255)->nullable();
            $t->string('method', 20)->nullable();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->string('frequency', 20)->default('monthly');
            $t->date('next_run_on');
            $t->date('last_run_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->string('file_path')->nullable();
            $t->string('file_name')->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('recurring_expense_profiles');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['twofa_secret', 'twofa_enabled', 'twofa_recovery_codes']);
        });
    }
};
