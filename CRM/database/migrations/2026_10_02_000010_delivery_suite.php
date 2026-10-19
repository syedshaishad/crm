<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->date('start_date')->nullable()->after('due_date');
            $t->date('baseline_due')->nullable()->after('start_date');
            $t->string('recurs', 20)->default('none')->after('baseline_due');
            $t->date('recurs_next')->nullable()->after('recurs');
            $t->string('public_token', 64)->nullable()->unique()->after('recurs_next');
        });
        Schema::create('task_dependencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['task_id', 'depends_on_task_id']);
        });
        Schema::create('task_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->string('priority', 20)->default('medium');
            $t->unsignedInteger('estimate_minutes')->default(0);
            $t->text('checklist')->nullable();
            $t->timestamps();
        });
        Schema::create('checklist_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->text('lines');
            $t->timestamps();
        });
        Schema::create('project_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->unsignedTinyInteger('score');
            $t->string('comment', 1000)->nullable();
            $t->string('author', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $t->string('title', 255);
            $t->string('severity', 20)->default('medium');
            $t->text('what_happened')->nullable();
            $t->text('root_cause')->nullable();
            $t->text('fix')->nullable();
            $t->string('status', 20)->default('open');
            $t->date('occurred_on')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('improvements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 255);
            $t->text('detail')->nullable();
            $t->string('status', 20)->default('proposed');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvements');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('project_feedback');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_dependencies');
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropColumn(['start_date', 'baseline_due', 'recurs', 'recurs_next', 'public_token']);
        });
    }
};
