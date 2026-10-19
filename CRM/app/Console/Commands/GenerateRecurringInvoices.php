<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Invoices\Invoice;
use Modules\Invoices\InvoiceMailer;
use Modules\Invoices\RecurringProfile;
use Modules\Settings\SettingStore;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring {--dry-run : List what would generate}';

    protected $description = 'Generate draft invoices from due recurring schedules.';

    public function handle(): int
    {
        $due = RecurringProfile::with('client')->where('status', 'active')
            ->whereDate('next_run_on', '<=', now()->toDateString())->get();
        if ($due->isEmpty()) {
            $this->info('No recurring schedules due.');

            return self::SUCCESS;
        }
        $s = app(SettingStore::class);
        $prefix = $s->get('invoice.prefix', 'INV-');
        $n = 0;
        foreach ($due as $p) {
            $label = ($p->client->company ?? $p->client->name ?? '?').' · '.$p->frequency.' · '.money(collect($p->items)->sum(fn ($it) => ($it['qty'] ?? 0) * ($it['rate'] ?? 0)));
            if ($this->option('dry-run')) {
                $this->line('Would generate: '.$label);

                continue;
            }
            $number = $prefix.str_pad((string) ((Invoice::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            // Trial: the house pays while the trial window is open.
            $onTrial = $p->trial_ends_on && $p->trial_ends_on->gte(now()->toDateString());
            // Proration: first run bills only the remaining share of the period.
            $factor = ($p->prorate_first && ! $p->last_run_on) ? $p->prorateFactor() : 1.0;
            $inv = Invoice::create([
                'number' => $number, 'client_id' => $p->client_id, 'project_id' => $p->project_id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) $s->get('invoice.due_days', 7))->toDateString(),
                'status' => 'draft', 'gst_rate' => $p->gst_rate,
                'notes' => trim(($p->notes ? $p->notes."\n" : '').'Auto-generated recurring invoice ('.$p->frequency.', '.now()->format('M Y').').'
                    .($onTrial ? ' Trial period — on the house.' : '').($factor < 1 ? ' Prorated first period ('.round($factor * 100).'%).' : '')),
                'place_of_supply' => $p->place_of_supply, 'discount' => $p->discount,
                'currency' => $p->currency ?? 'INR', 'exchange_rate' => $p->exchange_rate ?? 1,
                'created_by' => $p->created_by,
            ]);
            foreach ($p->items ?? [] as $it) {
                $qty = round(($it['qty'] ?? 0) * $factor, 2);
                $inv->items()->create([
                    'description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null,
                    'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null,
                    'qty' => max(0.01, $qty), 'rate' => $it['rate'],
                    'discount_percent' => $it['discount_percent'] ?? 0,
                    'amount' => round(max(0.01, $qty) * $it['rate'], 2),
                ]);
            }
            $inv->recalc();
            if ($onTrial) {
                $inv->update(['discount' => (float) $inv->subtotal]);
                $inv->recalc();
            }
            $p->advance();
            $n++;
            $this->info('Generated '.$number.' for '.$label);
            if ($p->auto_send && ! $this->option('dry-run')) {
                $inv->update(['status' => 'sent']);
                $inv->fresh()->syncStock();
                $res = InvoiceMailer::send($inv->fresh());
                $res['ok'] ? $this->info('Emailed '.$number.'.') : $this->error('Email failed for '.$number.': '.$res['message']);
            }
        }
        if (! $this->option('dry-run')) {
            $this->info("Done: {$n} invoice(s).");
        }

        return self::SUCCESS;
    }
}

