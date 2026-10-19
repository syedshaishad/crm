<?php

namespace Modules\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    protected $fillable = ['name', 'type', 'frequency', 'recipients', 'channel', 'is_active', 'last_run_on'];

    protected $casts = ['is_active' => 'boolean', 'last_run_on' => 'date'];

    public static function types(): array
    {
        return ['sales' => 'Sales summary', 'receivables' => 'Receivables (who owes)', 'pnl' => 'Profit & loss'];
    }

    public static function frequencies(): array
    {
        return ['weekly' => 'Weekly (Monday)', 'monthly' => 'Monthly (1st)'];
    }

    /** Due to run today? Weekly fires Monday, monthly on the 1st, once each. */
    public function isDue(string $today): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->last_run_on && $this->last_run_on->toDateString() === $today) {
            return false;
        }
        $d = \Carbon\Carbon::parse($today);
        if ($this->frequency === 'weekly') {
            return $d->isMonday();
        }

        return $d->day === 1;
    }

    /** Build subject + plain-text lines for the covered period. */
    public function compile(string $today): array
    {
        $end = \Carbon\Carbon::parse($today);
        if ($this->frequency === 'weekly') {
            $from = $end->copy()->subDays(6)->toDateString();
            $label = $end->copy()->subDays(6)->format('j M').' – '.$end->format('j M Y');
        } else {
            $from = $end->copy()->subMonth()->startOfMonth()->toDateString();
            $endS = $end->copy()->subMonth()->endOfMonth();
            $label = $endS->format('F Y');
            $end = $endS;
        }
        $to = $end->toDateString();
        $lines = [];
        if ($this->type === 'sales') {
            $billed = (float) \DB::table('invoices')->whereNotIn('status', ['cancelled', 'draft'])->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->sum('total');
            $got = (float) \DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->sum('amount');
            $n = (int) \DB::table('invoices')->whereNotIn('status', ['cancelled', 'draft'])->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->count();
            $lines[] = 'Billed '.money($billed).' across '.$n.' invoice(s).';
            $lines[] = 'Collected '.money($got).'.';
            $top = \DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')->whereNotIn('invoices.status', ['cancelled', 'draft'])->whereDate('invoices.issue_date', '>=', $from)->whereDate('invoices.issue_date', '<=', $to)->selectRaw('COALESCE(clients.company, clients.name) as who, SUM(invoices.total) as t')->groupBy('who')->orderByDesc('t')->limit(5)->get();
            foreach ($top as $t) {
                $lines[] = '• '.$t->who.' — '.money($t->t);
            }
            $subject = 'Sales '.$this->frequency.' — '.$label.': '.money($billed).' billed';
        } elseif ($this->type === 'receivables') {
            $rows = \DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')->where('invoices.status', 'overdue')->orderBy('invoices.due_date')->limit(25)->selectRaw('invoices.number, COALESCE(clients.company, clients.name) as who, invoices.total, invoices.paid_amount, invoices.due_date')->get();
            $tot = 0;
            foreach ($rows as $r) {
                $bal = max(0, (float) $r->total - (float) $r->paid_amount);
                $tot += $bal;
                $lines[] = '• '.$r->number.' — '.$r->who.' owes '.money($bal).' (due '.d($r->due_date, 'j M Y').')';
            }
            if (empty($lines)) {
                $lines[] = 'Nothing overdue — books are clean.';
            }
            $subject = 'Receivables — '.money($tot).' overdue ('.count($rows).')';
        } else {
            $rev = (float) \DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->sum('amount');
            $exp = (float) \DB::table('expenses')->where('status', 'approved')->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->sum('amount');
            $lines[] = 'Revenue '.money($rev).' · Expenses '.money($exp).' · Profit '.money(round($rev - $exp, 2)).'.';
            $byCat = \DB::table('expenses')->where('status', 'approved')->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->limit(6)->get();
            foreach ($byCat as $c) {
                $lines[] = '• '.ucfirst($c->category ?? 'other').' — '.money($c->t);
            }
            $subject = 'P&L '.$label.': profit '.money(round($rev - $exp, 2));
        }

        return [$subject, $lines, $label];
    }
}
