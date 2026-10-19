<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedSmallInteger('capacity_hours')->nullable()->after('exit_date');
        });
        Schema::create('skills', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120)->unique();
            $t->timestamps();
        });
        Schema::create('user_skills', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $t->unsignedTinyInteger('level')->default(1);
            $t->boolean('certified')->default(false);
            $t->date('cert_expires_on')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'skill_id']);
        });
        Schema::create('trainings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 200);
            $t->date('completed_on');
            $t->date('valid_till')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('field_visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $t->timestamp('check_in_at');
            $t->decimal('in_lat', 10, 7)->nullable();
            $t->decimal('in_lng', 10, 7)->nullable();
            $t->timestamp('check_out_at')->nullable();
            $t->decimal('out_lat', 10, 7)->nullable();
            $t->decimal('out_lng', 10, 7)->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
        Schema::create('suggestions', function (Blueprint $t) {
            $t->id();
            $t->text('body');
            $t->string('mood', 20)->nullable();
            $t->string('status', 20)->default('new');
            $t->timestamps();
        });
        Schema::create('polls', function (Blueprint $t) {
            $t->id();
            $t->string('question', 255);
            $t->date('month')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('poll_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $t->unsignedTinyInteger('score');
            $t->string('comment', 500)->nullable();
            $t->string('voter_hash', 64);
            $t->timestamps();
            $t->unique(['poll_id', 'voter_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('field_visits');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('skills');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('capacity_hours');
        });
    }
};
