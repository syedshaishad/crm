<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->unique()->after('id');
        });
        // Backfill existing rows so every payment has a trackable receipt number.
        $prefix = 'PAY-';
        try {
            $row = DB::table('settings')->where('key', 'payments.prefix')->first();
            if ($row && ! empty($row->value)) {
                $prefix = $row->value;
            }
        } catch (Throwable $e) {
        }
        foreach (DB::table('payments')->orderBy('id')->get(['id']) as $p) {
            DB::table('payments')->where('id', $p->id)->update([
                'number' => $prefix.str_pad((string) $p->id, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
