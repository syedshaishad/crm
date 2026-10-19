<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('minutes');
            $table->date('worked_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('custom_fields');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_times');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
};
