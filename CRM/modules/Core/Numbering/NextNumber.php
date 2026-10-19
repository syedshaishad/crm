<?php

namespace Modules\Core\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers (INV-00001 style) in one place.
 *
 * Every caller used to duplicate `prefix.str_pad((max(id)+1), 5, '0', ...)`.
 * This keeps that behavior but allocates inside a transaction with an atomic
 * lock (file/array stores in this app both support it), so two users saving
 * at the same moment can no longer mint the same number.
 */
class NextNumber
{
    /** Allocate and return just the number (used when the caller does the insert itself). */
    public static function take(string $prefix, string $table): string
    {
        $lock = cache()->lock('numbering:'.$table, 10);
        try {
            $lock->block(5);
        } catch (\Throwable) {
        }
        try {
            return DB::transaction(fn () => self::allocate($prefix, $table));
        } finally {
            optional($lock)->release();
        }
    }

    /** The one policy: continuous max(id)+1, or per-FY series when enabled. */
    protected static function allocate(string $prefix, string $table): string
    {
        if (function_exists('fy') && function_exists('setting') && setting('numbering.mode', 'continuous') === 'fy') {
            $fy = fy();
            $base = rtrim(trim($prefix), '-_/ ') ?: 'DOC';
            $max = 0;
            try {
                // Query builder sees soft-deleted rows too — trashed numbers
                // are never reused, same guarantee as the continuous path.
                $nums = DB::table($table)->where('number', 'like', $base.'/'.$fy.'/%')->pluck('number');
            } catch (\Throwable) {
                $nums = [];
            }
            foreach ($nums as $n) {
                if (preg_match('#/(\d+)$#', (string) $n, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }

            return $base.'/'.$fy.'/'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
        }
        $max = (int) (DB::table($table)->max('id') ?? 0);

        return $prefix.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }
}
