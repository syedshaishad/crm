<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable()->after('description');
            $table->string('unit', 10)->default('NOS')->after('qty');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('place_of_supply', 100)->nullable()->after('gst_rate');
            $table->boolean('reverse_charge')->default(false)->after('place_of_supply');
            $table->decimal('discount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('taxable_value', 14, 2)->default(0)->after('discount');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->string('state', 100)->nullable()->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('state');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['place_of_supply', 'reverse_charge', 'discount', 'taxable_value']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'unit']);
        });
    }
};
