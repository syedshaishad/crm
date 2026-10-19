<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('opening_stock', 12, 2)->default(0)->after('unit');
            $table->decimal('low_stock_at', 12, 2)->nullable()->after('opening_stock');
        });
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // in = purchase/production, out = sale/issue, adjustment = signed correction.
            $table->string('type', 20)->default('in');
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->date('moved_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['opening_stock', 'low_stock_at']);
        });
    }
};
