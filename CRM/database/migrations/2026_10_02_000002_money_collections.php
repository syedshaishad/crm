<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->decimal('writeoff_amount', 12, 2)->default(0)->after('paid_amount');
            $t->unsignedTinyInteger('dunning_level')->default(0)->after('last_nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['writeoff_amount', 'dunning_level']);
        });
    }
};
