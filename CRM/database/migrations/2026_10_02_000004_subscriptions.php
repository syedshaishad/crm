<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_profiles', function (Blueprint $t) {
            $t->string('status', 20)->default('active')->after('is_active');
            $t->date('trial_ends_on')->nullable()->after('status');
            $t->boolean('prorate_first')->default(false)->after('trial_ends_on');
            $t->unsignedTinyInteger('billing_day')->nullable()->after('prorate_first');
        });
        DB::table('recurring_profiles')->where('is_active', false)->update(['status' => 'paused']);
    }

    public function down(): void
    {
        Schema::table('recurring_profiles', function (Blueprint $t) {
            $t->dropColumn(['status', 'trial_ends_on', 'prorate_first', 'billing_day']);
        });
    }
};
