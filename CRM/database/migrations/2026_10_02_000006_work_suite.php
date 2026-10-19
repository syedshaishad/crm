<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->unsignedInteger('estimate_minutes')->default(0)->after('priority');
            $t->decimal('hourly_rate', 10, 2)->nullable()->after('estimate_minutes');
            $t->string('handover_note', 255)->nullable()->after('due_date');
            $t->string('handed_over_to', 255)->nullable()->after('handover_note');
            $t->timestamp('handed_over_at')->nullable()->after('handed_over_to');
            $t->foreignId('handed_over_by')->nullable()->after('handed_over_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('task_times', function (Blueprint $t) {
            $t->foreignId('invoice_id')->nullable()->after('note')->constrained('invoices')->nullOnDelete();
        });
        Schema::create('task_checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->string('label', 255);
            $t->boolean('is_done')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::create('leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->string('type', 20)->default('casual');
            $t->string('reason', 500)->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('day');
            $t->string('label', 60);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'day']);
        });
        Schema::create('appraisals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('period', 20);
            $t->unsignedTinyInteger('rating');
            $t->text('strengths')->nullable();
            $t->text('improvements')->nullable();
            $t->text('goals')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('task_checklists');
        Schema::table('task_times', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invoice_id');
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('handed_over_by');
            $t->dropColumn(['estimate_minutes', 'hourly_rate', 'handover_note', 'handed_over_to', 'handed_over_at']);
        });
    }
};
