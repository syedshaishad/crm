<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('route', 120);
            $t->string('name', 80);
            $t->json('query');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'route', 'name']);
            $t->index(['user_id', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
