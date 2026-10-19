<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_closes', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->decimal('cash_counted', 14, 2);
            $table->decimal('expected_petty', 14, 2);
            $table->decimal('cash_receipts', 14, 2)->default(0);
            $table->json('checks')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closes');
    }
};
