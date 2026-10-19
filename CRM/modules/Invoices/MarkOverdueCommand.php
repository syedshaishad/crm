<?php

namespace Modules\Invoices;

use App\Models\User;
use App\Notifications\InvoiceOverdue;
use Illuminate\Console\Command;

class MarkOverdueCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent/partial invoices past due date as overdue.';

    public function handle(): int
    {
        $ids = Invoice::whereIn('status', ['sent', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereColumn('paid_amount', '<', 'total')
            ->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Marked 0 invoice(s) overdue.');

            return self::SUCCESS;
        }
        Invoice::whereIn('id', $ids)->update(['status' => 'overdue']);

        // Tell admins what just slipped past due (console bypasses notify_roles).
        $admins = User::where('is_active', true)->where('role', 'admin')->get();
        foreach (Invoice::whereIn('id', $ids)->get() as $inv) {
            foreach ($admins as $a) {
                $a->notify(new InvoiceOverdue($inv));
            }
        }
        $this->info("Marked {$ids->count()} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
