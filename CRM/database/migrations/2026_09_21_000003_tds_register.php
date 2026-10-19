<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_entries', function (Blueprint $table) {
            $table->id();
            // receivable = client deducted on our invoice; payable = we deducted on a vendor expense.
            $table->string('kind', 20)->default('receivable');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('vendor_name', 255)->nullable();
            $table->string('section', 20)->default('194C');
            $table->decimal('rate', 5, 2)->default(0);
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('deducted_on');
            $table->string('fy', 10)->default('');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->string('pan', 10)->nullable()->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_entries');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('pan');
        });
    }
};
