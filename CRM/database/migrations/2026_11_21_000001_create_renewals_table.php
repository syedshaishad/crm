<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewals', function (Blueprint $t) {
            $t->id();
            $t->string('source_type', 20);
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('client_id')->nullable();
            $t->string('title', 255);
            $t->date('renewal_date')->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('status', 20)->default('upcoming');
            $t->unsignedBigInteger('proposal_id')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['source_type', 'source_id']);
            $t->index(['status', 'renewal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewals');
    }
};
