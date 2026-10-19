<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('designation', 120)->nullable()->after('role');
            $t->string('department', 120)->nullable()->after('designation');
            $t->string('phone', 20)->nullable()->after('email');
            $t->string('address', 255)->nullable()->after('phone');
            $t->date('joining_date')->nullable()->after('address');
            $t->date('exit_date')->nullable()->after('joining_date');
        });
        Schema::table('assets', function (Blueprint $t) {
            $t->foreignId('assigned_to')->nullable()->after('location')->constrained('users')->nullOnDelete();
        });
        Schema::create('salary_structures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('effective_from');
            $t->decimal('basic', 12, 2)->default(0);
            $t->decimal('hra', 12, 2)->default(0);
            $t->decimal('allowances', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->date('month')->unique();
            $t->string('status', 20)->default('draft');
            $t->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });
        Schema::create('payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedInteger('working_days')->default(0);
            $t->decimal('paid_days', 5, 2)->default(0);
            $t->decimal('gross_monthly', 12, 2)->default(0);
            $t->decimal('gross_earned', 12, 2)->default(0);
            $t->decimal('overtime_pay', 12, 2)->default(0);
            $t->decimal('bonus', 12, 2)->default(0);
            $t->decimal('reimbursements', 12, 2)->default(0);
            $t->decimal('advance_cut', 12, 2)->default(0);
            $t->decimal('loan_cut', 12, 2)->default(0);
            $t->decimal('tds', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->timestamps();
            $t->unique(['payroll_id', 'user_id']);
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('day')->unique();
            $t->string('name', 120);
            $t->timestamps();
        });
        Schema::create('overtime_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('minutes');
            $t->string('note', 255)->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('staff_loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('principal', 12, 2);
            $t->decimal('emi', 12, 2);
            $t->date('disbursed_on');
            $t->string('status', 20)->default('active');
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('loan_repayments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained('staff_loans')->cascadeOnDelete();
            $t->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $t->decimal('amount', 12, 2);
            $t->date('paid_on');
            $t->timestamps();
        });
        Schema::create('bonuses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('month');
            $t->decimal('amount', 12, 2);
            $t->string('reason', 255)->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('reimbursements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('spent_on');
            $t->decimal('amount', 12, 2);
            $t->string('reason', 255)->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('exits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('resigned_on')->nullable();
            $t->date('last_day')->nullable();
            $t->string('reason', 500)->nullable();
            $t->string('status', 20)->default('pending');
            $t->text('clearance')->nullable();
            $t->timestamps();
            $t->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exits');
        Schema::dropIfExists('reimbursements');
        Schema::dropIfExists('bonuses');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('staff_loans');
        Schema::dropIfExists('overtime_entries');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('salary_structures');
        Schema::table('assets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_to');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['designation', 'department', 'phone', 'address', 'joining_date', 'exit_date']);
        });
    }
};
