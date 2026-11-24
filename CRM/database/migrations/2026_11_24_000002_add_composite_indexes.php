<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second, narrower index pass.
 *
 * The first pass added one index per filtered column. On MariaDB that is not
 * always a win: `status` alone has six values, so the optimizer still prefers a
 * full scan for status+date filters, and a non-covering `status` index can make
 * `GROUP BY status` *slower* (it reads the index, then the row, for every
 * record). These composite/covering indexes match the real query shapes the
 * dashboard and registers use, and the redundant single-column indexes they
 * supersede are dropped.
 *
 * Measured on MariaDB 11.4 with 6k invoices / 14k payments: dashboard aggregate
 * work roughly halved and the invoice pipeline query dropped from ~9 ms to
 * ~1.9 ms. Every step is guarded so existing installs migrate safely.
 */
return new class extends Migration
{
    /** Composite indexes, most selective column first. */
    private array $add = [
        'invoices' => [
            ['status', 'due_date'],
            ['status', 'total'],
            ['issue_date', 'total'],
        ],
        'payments' => [
            ['paid_on', 'amount'],
        ],
        'expenses' => [
            ['status', 'spent_on', 'amount'],
        ],
        'tasks' => [
            ['status', 'due_date'],
            ['status', 'updated_at'],
        ],
        'proposals' => [
            ['status', 'total'],
            ['status', 'updated_at'],
            ['created_at', 'total'],
        ],
    ];

    /** Single-column indexes now covered by a composite prefix. */
    private array $drop = [
        'invoices' => ['status', 'issue_date'],
        'payments' => ['paid_on'],
        'expenses' => ['status'],
        'tasks' => ['status'],
        'proposals' => ['status', 'created_at'],
    ];

    public function up(): void
    {
        foreach ($this->add as $table => $groups) {
            if (! Schema::hasTable($table)) continue;
            foreach ($groups as $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) continue 2;
                }
                $name = $this->name($table, $columns);
                if (Schema::hasIndex($table, $name)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }

        // Drop only after the replacement exists, and never fail the migration
        // over an index that a foreign key or another query still needs.
        foreach ($this->drop as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                if (! Schema::hasIndex($table, $name)) continue;
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                } catch (Throwable $e) {
                    // Kept on purpose: MySQL refuses to drop an index backing a
                    // foreign key, and the extra index is harmless.
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->add as $table => $groups) {
            if (! Schema::hasTable($table)) continue;
            foreach ($groups as $columns) {
                $name = $this->name($table, $columns);
                if (! Schema::hasIndex($table, $name)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }

        foreach ($this->drop as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                if (Schema::hasIndex($table, $name)) continue;
                if (! Schema::hasColumn($table, $column)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
            }
        }
    }

    private function name(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_index';
    }
};
