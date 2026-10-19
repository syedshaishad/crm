<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->string('currency', 3)->default('INR')->after('loyalty_balance');
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->string('currency', 3)->default('INR')->after('dunning_level');
            $t->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });
        Schema::table('recurring_profiles', function (Blueprint $t) {
            $t->string('currency', 3)->default('INR')->after('billing_day');
            $t->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->dropColumn('currency');
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['currency', 'exchange_rate']);
        });
        Schema::table('recurring_profiles', function (Blueprint $t) {
            $t->dropColumn(['currency', 'exchange_rate']);
        });
    }
};
