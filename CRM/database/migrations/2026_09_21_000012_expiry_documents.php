<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('title', 255);
            $table->string('category', 30)->default('other');
            $table->string('ref_no', 120)->nullable();
            $table->string('authority', 160)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('remind_days')->default(30);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_documents');
    }
};
