<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the filters and date ranges used by the dashboard and
     * register screens. Existing installs get them on the next migrate.
     */
    public function up(): void
    {
        $indexes = [
            'invoices' => ['status', 'due_date', 'client_id', 'issue_date'],
            'payments' => ['paid_on', 'invoice_id', 'client_id'],
            'expenses' => ['status', 'spent_on'],
            'tasks' => ['status', 'due_date'],
            'proposals' => ['status', 'updated_at', 'created_at'],
            'leads' => ['stage', 'created_at'],
            'clients' => ['status'],
            'credit_notes' => ['status'],
            'pdc_cheques' => ['status'],
            'purchase_orders' => ['status'],
        ];

        foreach ($indexes as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) continue;
                $name = "{$table}_{$column}_index";
                if (Schema::hasIndex($table, $name)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
            }
        }
    }

    public function down(): void
    {
        // Indexes are harmless to keep; dropping them would slow reads down.
    }
};
