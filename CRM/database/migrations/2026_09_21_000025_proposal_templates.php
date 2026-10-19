<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('place_of_supply', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('scope')->nullable();
            $table->text('results')->nullable();
            $table->text('terms')->nullable();
            $table->json('items')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_templates');
    }
};
