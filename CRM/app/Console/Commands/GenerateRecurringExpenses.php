<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Expenses\Expense;
use Modules\Expenses\RecurringExpenseProfile;
use Modules\Settings\SettingStore;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring {--dry-run : List what would generate}';

    protected $description = 'Generate expense entries from due recurring schedules.';

    public function handle(): int
    {
        $due = RecurringExpenseProfile::where('is_active', true)
            ->whereDate('next_run_on', '<=', now()->toDateString())->get();
        if ($due->isEmpty()) {
            $this->info('No recurring expenses due.');

            return self::SUCCESS;
        }
        $s = app(SettingStore::class);
        $prefix = $s->get('expense.prefix', 'EXP-');
        $n = 0;
        foreach ($due as $p) {
            $label = $p->title.' · '.$p->frequency.' · '.money($p->amount);
            if ($this->option('dry-run')) {
                $this->line('Would generate: '.$label);

                continue;
            }
            $en = (Expense::withTrashed()->max('id') ?? 0) + 1 + $n;
            Expense::create([
                'number' => $prefix.str_pad((string) $en, 5, '0', STR_PAD_LEFT),
                'title' => $p->title, 'category' => $p->category ?? 'general', 'amount' => $p->amount,
                'spent_on' => now()->toDateString(), 'vendor' => $p->vendor, 'method' => $p->method,
                'project_id' => $p->project_id,
                'notes' => trim(($p->notes ? $p->notes."\n" : '').'Auto-generated recurring expense ('.$p->frequency.', '.now()->format('M Y').').'),
                'created_by' => $p->created_by,
            ]);
            $p->advance();
            $n++;
            $this->info('Generated '.$label);
        }
        if (! $this->option('dry-run')) {
            $this->info("Done: {$n} expense(s).");
        }

        return self::SUCCESS;
    }
}

