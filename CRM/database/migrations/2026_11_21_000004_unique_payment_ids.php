<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Payments\Payment;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the earliest copy of each duplicate gateway payment so the
        // unique index can be added safely on existing installations.
        $duplicates = DB::table('payments')
            ->select('razorpay_payment_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('razorpay_payment_id')
            ->groupBy('razorpay_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicates as $dup) {
            Payment::where('razorpay_payment_id', $dup->razorpay_payment_id)
                ->where('id', '!=', $dup->keep_id)->get()
                ->each(fn (Payment $p) => $p->delete()); // model events recalc invoices
        }
        Schema::table('payments', fn (Blueprint $t) => $t->unique('razorpay_payment_id'));
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropUnique(['razorpay_payment_id']));
    }
};
