<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('route_date');
            $table->string('vehicle_no', 40)->nullable();
            $table->string('driver', 120)->nullable();
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('delivery_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('challan_id')->constrained('delivery_challans')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->unique(['route_id', 'challan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_stops');
        Schema::dropIfExists('delivery_routes');
    }
};
