<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->decimal('discount_percent', 5, 2)->default(0)->after('rate');
        });
        Schema::table('proposal_items', function (Blueprint $t) {
            $t->decimal('discount_percent', 5, 2)->default(0)->after('rate');
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->decimal('loyalty_balance', 12, 2)->default(0)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->dropColumn('discount_percent');
        });
        Schema::table('proposal_items', function (Blueprint $t) {
            $t->dropColumn('discount_percent');
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->dropColumn('loyalty_balance');
        });
    }
};
