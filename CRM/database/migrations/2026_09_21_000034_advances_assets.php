<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 10); // advance | recovery
            $table->decimal('amount', 14, 2);
            $table->date('dated');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->date('bought_on');
            $table->decimal('cost', 14, 2);
            $table->unsignedSmallInteger('life_years')->default(5);
            $table->string('location', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('assets');
    }
};
