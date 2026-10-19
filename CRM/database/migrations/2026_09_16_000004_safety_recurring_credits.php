<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-delete (trash) for restorable records. Clients/Projects stay
        // hard-delete (DB cascades); everything below restores cleanly.
        foreach (['tasks', 'invoices', 'invoice_items', 'payments', 'expenses', 'documents', 'leads', 'proposals', 'proposal_items', 'users'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor', 100)->default('staff');
            $t->string('action', 20);
            $t->string('subject_type', 60);
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('label', 255)->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
            $t->index('created_at');
        });

        Schema::create('mail_logs', function (Blueprint $t) {
            $t->id();
            $t->string('to', 500)->nullable();
            $t->string('subject', 255)->nullable();
            $t->string('template', 100)->nullable();
            $t->string('status', 20)->default('sent');
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('recurring_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->string('frequency', 20)->default('monthly');
            $t->date('next_run_on');
            $t->date('last_run_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->decimal('gst_rate', 5, 2)->default(18);
            $t->decimal('discount', 12, 2)->default(0);
            $t->string('place_of_supply', 100)->nullable();
            $t->text('notes')->nullable();
            $t->json('items')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->date('date')->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->decimal('applied_amount', 12, 2)->default(0);
            $t->string('status', 20)->default('open');
            $t->text('reason')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('recurring_profiles');
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('activities');
        foreach (['tasks', 'invoices', 'invoice_items', 'payments', 'expenses', 'documents', 'leads', 'proposals', 'proposal_items', 'users'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
