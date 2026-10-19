<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku', 40)->nullable();
                $table->text('description')->nullable();
                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->nullable();
                $table->string('hsn_code', 20)->nullable();
                $table->string('unit', 10)->default('NOS');
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
