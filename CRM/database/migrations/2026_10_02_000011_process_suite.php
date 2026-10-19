<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('trigger', 40);
            $t->unsignedInteger('after_days')->default(0);
            $t->string('action', 40);
            $t->foreignId('reassign_to')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('automation_firings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('workflow_rules')->cascadeOnDelete();
            $t->string('subject_type', 40);
            $t->unsignedBigInteger('subject_id');
            $t->date('fired_on');
            $t->timestamps();
            $t->unique(['rule_id', 'subject_type', 'subject_id', 'fired_on']);
        });
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->string('title', 255);
            $t->timestamp('starts_at');
            $t->unsignedInteger('duration_minutes')->default(30);
            $t->text('attendees')->nullable();
            $t->text('agenda')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('title', 255);
            $t->string('category', 80)->default('SOP');
            $t->text('body');
            $t->boolean('pinned')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_firings');        Schema::dropIfExists('articles');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('workflow_rules');
    }
};
