<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: an earlier interrupted run may have created some of these
        // tables without recording the migration, which made every retry die on
        // "Base table or view already exists" (MySQL 1050).
        if (! Schema::hasTable('workflow_rules')) {
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
        }
        if (! Schema::hasTable('automation_firings')) {
            Schema::create('automation_firings', function (Blueprint $t) {
                $t->id();
                $t->foreignId('rule_id')->constrained('workflow_rules')->cascadeOnDelete();
                $t->string('subject_type', 40);
                $t->unsignedBigInteger('subject_id');
                $t->date('fired_on');
                $t->timestamps();
                $t->unique(['rule_id', 'subject_type', 'subject_id', 'fired_on'], 'automation_firings_rule_subject_fired_unique');
            });
        }
        if (Schema::hasTable('automation_firings')) {
            // The generated name for this index is 66 chars on MySQL (limit 64).
            // An interrupted earlier run can leave the table without the index.
            $hasUnique = collect(Schema::getIndexes('automation_firings'))->contains(
                fn (array $index) => ($index['unique'] ?? false)
                    && ($index['columns'] ?? []) === ['rule_id', 'subject_type', 'subject_id', 'fired_on']
            );
            if (! $hasUnique) {
                Schema::table('automation_firings', function (Blueprint $t) {
                    $t->unique(['rule_id', 'subject_type', 'subject_id', 'fired_on'], 'automation_firings_rule_subject_fired_unique');
                });
            }
        }
        if (! Schema::hasTable('meetings')) {
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
        }
        if (! Schema::hasTable('articles')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_firings');        Schema::dropIfExists('articles');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('workflow_rules');
    }
};
