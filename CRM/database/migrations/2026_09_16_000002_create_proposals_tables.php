<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('draft'); // draft|sent|accepted|declined|expired|invoiced
            $t->date('issue_date')->nullable();
            $t->date('valid_until')->nullable();
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('taxable_value', 12, 2)->default(0);
            $t->decimal('gst_rate', 5, 2)->default(18);
            $t->decimal('gst_amount', 12, 2)->default(0);
            $t->decimal('cgst_amount', 12, 2)->default(0);
            $t->decimal('sgst_amount', 12, 2)->default(0);
            $t->decimal('igst_amount', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->string('place_of_supply', 100)->nullable();
            $t->boolean('reverse_charge')->default(false);
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->string('sign_token', 64)->nullable()->unique();
            $t->string('signed_by')->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->string('signature_path')->nullable();
            $t->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('proposal_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $t->string('description');
            $t->string('hsn_code', 20)->nullable();
            $t->decimal('qty', 12, 2)->default(1);
            $t->string('unit', 10)->default('NOS');
            $t->decimal('rate', 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposals');
    }
};
