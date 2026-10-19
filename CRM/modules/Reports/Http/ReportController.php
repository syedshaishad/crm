<?php

namespace Modules\Reports\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Gst\Calculator;
use Modules\Invoices\Invoice;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:reports');
    }
    public function index(Request $r)
    {
        [$from, $to] = $this->period($r);
        $revenue = DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->sum('amount');
        $expenses = DB::table('expenses')->where('status','approved')->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->sum('amount');
        $byStatus = DB::table('invoices')->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(total) t'))->groupBy('status')->get();
        $byCategory = DB::table('expenses')->where('status','approved')->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->select('category', DB::raw('COUNT(*) c'), DB::raw('SUM(amount) t'))->groupBy('category')->orderByDesc('t')->get();
        // Revenue trend: last 6 months for the SVG line.
        $trend = [];
        for ($m = 5; $m >= 0; $m--) {
            $dd = now()->subMonths($m);
            $trend[] = ['label' => $dd->format('M'), 'value' => (float) DB::table('payments')->whereYear('paid_on', $dd->year)->whereMonth('paid_on', $dd->month)->sum('amount')];
        }
        $trendMax = max(1, collect($trend)->max('value'));

        return view('reports.index', compact('from', 'to', 'revenue', 'expenses', 'byStatus', 'byCategory', 'trend', 'trendMax'));
    }

    /** Tabular breakdowns: status, top clients, expenses by project. */
    public function breakdown(Request $r)
    {
        [$from, $to] = $this->period($r);
        $byStatus = DB::table('invoices')->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(total) t'))->groupBy('status')->orderByDesc('t')->get();
        $byClient = DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereDate('invoices.issue_date', '>=', $from)->whereDate('invoices.issue_date', '<=', $to)
            ->select('clients.name', DB::raw('COUNT(*) invoices'), DB::raw('SUM(invoices.total) total'))
            ->groupBy('clients.name')->orderByDesc('total')->limit(10)->get();
        $byProject = DB::table('expenses')->where('expenses.status', 'approved')->leftJoin('projects', 'projects.id', '=', 'expenses.project_id')
            ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->select(DB::raw("COALESCE(projects.name,'General') as project"), DB::raw('SUM(expenses.amount) t'))->groupBy('project')->orderByDesc('t')->get();
        $byMethod = DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)
            ->select('method', DB::raw('COUNT(*) c'), DB::raw('SUM(amount) t'))->groupBy('method')->orderByDesc('t')->get();
        $projBilled = DB::table('invoices')->leftJoin('projects', 'projects.id', '=', 'invoices.project_id')
            ->whereIn('invoices.status', ['sent', 'partial', 'paid', 'overdue'])
            ->whereDate('invoices.issue_date', '>=', $from)->whereDate('invoices.issue_date', '<=', $to)
            ->select(DB::raw("COALESCE(projects.name,'General') as project"), DB::raw('SUM(invoices.total) b'))->groupBy('project')->pluck('b', 'project');

        return view('reports.breakdown', compact('from', 'to', 'byStatus', 'byClient', 'byProject', 'projBilled', 'byMethod'));
    }

    /** Every download in one place, honouring the period filter. */
    public function exports(Request $r)
    {
        [$from, $to] = $this->period($r);
        $ledgerClients = DB::table('clients')->orderBy('company')->orderBy('name')->get(['id', 'name', 'company']);

        return view('reports.exports', compact('from', 'to', 'ledgerClients'));
    }

    /** Staff performance: collections credited to the invoice assignee + billing by creator, vs monthly targets. */
    public function staff(Request $r)
    {
        $month = $r->input('month', now()->format('Y-m'));
        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();

        $collected = DB::table('payments')->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->whereDate('payments.paid_on', '>=', $from)->whereDate('payments.paid_on', '<=', $to)
            ->select('invoices.assigned_to as uid', DB::raw('SUM(payments.amount) t'), DB::raw('COUNT(*) c'))
            ->groupBy('invoices.assigned_to')->get()->keyBy(fn ($x) => $x->uid ?? 0);
        $billed = DB::table('invoices')->where('status', '!=', 'cancelled')
            ->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)
            ->select('created_by as uid', DB::raw('SUM(total) t'), DB::raw('COUNT(*) c'))
            ->groupBy('created_by')->get()->keyBy('uid');

        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role', 'monthly_target', 'commission_rate']);
        $rows = $users->map(fn ($u) => [
            'user' => $u,
            'collected' => (float) ($collected[$u->id]->t ?? 0),
            'receipts' => (int) ($collected[$u->id]->c ?? 0),
            'billed' => (float) ($billed[$u->id]->t ?? 0),
            'invoices' => (int) ($billed[$u->id]->c ?? 0),
        ])->sortByDesc('collected')->values();
        $unassigned = ['collected' => (float) ($collected[0]->t ?? 0), 'receipts' => (int) ($collected[0]->c ?? 0)];
        $unbilled = ['billed' => (float) DB::table('invoices')->where('status', '!=', 'cancelled')->whereNull('created_by')
            ->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->sum('total')];
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $start->copy()->addMonth()->gt(now()->startOfMonth()) ? null : $start->copy()->addMonth()->format('Y-m');

        return view('reports.staff', compact('month', 'from', 'to', 'rows', 'unassigned', 'unbilled', 'prev', 'next'));
    }

    /** Tally Prime import XML: masters + balanced Sales/Purchase vouchers. */
    public function tally(Request $r)
    {
        [$from, $to] = $this->period($r);
        $xml = \Modules\Reports\Tally\TallyExport::xml($from, $to);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, "tally-{$from}-to-{$to}.xml", ['Content-Type' => 'text/xml; charset=UTF-8']);
    }

    /** Proposal analytics: funnel, win rate, top clients, ageing of open quotes. */
    public function proposals(Request $r)
    {
        [$from, $to] = $this->period($r);
        $rows = DB::table('proposals')->join('clients', 'clients.id', '=', 'proposals.client_id')
            ->whereDate('proposals.issue_date', '>=', $from)->whereDate('proposals.issue_date', '<=', $to)
            ->select('proposals.number', 'proposals.status', 'proposals.total', 'proposals.issue_date', 'proposals.valid_until', 'proposals.lost_reason', 'proposals.competitor', 'proposals.close_reason', 'clients.name', 'clients.company')
            ->orderByDesc('proposals.total')->get();
        $today = now()->toDateString();
        $eff = fn ($p) => ($p->status === 'sent' && $p->valid_until && $p->valid_until < $today) ? 'expired' : $p->status;
        $funnel = [];
        foreach ($rows as $p) {
            $s = $eff($p);
            $funnel[$s] = $funnel[$s] ?? ['c' => 0, 't' => 0];
            $funnel[$s]['c']++;
            $funnel[$s]['t'] += (float) $p->total;
        }
        $won = ($funnel['accepted']['t'] ?? 0) + ($funnel['invoiced']['t'] ?? 0);
        $wonC = ($funnel['accepted']['c'] ?? 0) + ($funnel['invoiced']['c'] ?? 0);
        $lost = ($funnel['declined']['t'] ?? 0) + ($funnel['expired']['t'] ?? 0);
        $decided = $won + $lost;
        $decidedC = $wonC + ($funnel['declined']['c'] ?? 0) + ($funnel['expired']['c'] ?? 0);
        $byClient = $rows->groupBy(fn ($p) => $p->company ?: $p->name)->map(fn ($g) => ['c' => $g->count(), 't' => $g->sum('total')])->sortByDesc('t')->take(8);
        // Win-loss: declined grouped by structured reason + named competitors.
        $reasons = \Modules\Proposals\Proposal::lostReasons();
        $byReason = [];
        $byCompetitor = [];
        $lostT = 0;
        foreach ($rows as $p) {
            if ($eff($p) !== 'declined') {
                continue;
            }
            $rk = $p->lost_reason ?: 'unknown';
            $byReason[$rk] = $byReason[$rk] ?? ['c' => 0, 't' => 0];
            $byReason[$rk]['c']++;
            $byReason[$rk]['t'] += (float) $p->total;
            $lostT += (float) $p->total;
            if ($p->competitor) {
                $ck = trim($p->competitor);
                $byCompetitor[$ck] = $byCompetitor[$ck] ?? ['c' => 0, 't' => 0];
                $byCompetitor[$ck]['c']++;
                $byCompetitor[$ck]['t'] += (float) $p->total;
            }
        }
        arsort($byCompetitor);
        $byCompetitor = array_slice($byCompetitor, 0, 8, true);
        $buckets = ['0–7 days' => 0, '8–15 days' => 0, '16–30 days' => 0, '30+ days' => 0];
        $ageingT = 0;
        foreach ($rows as $p) {
            if ($eff($p) !== 'sent') {
                continue;
            }
            $age = max(0, (int) now()->diffInDays($p->issue_date, true));
            $b = $age <= 7 ? '0–7 days' : ($age <= 15 ? '8–15 days' : ($age <= 30 ? '16–30 days' : '30+ days'));
            $buckets[$b]++;
            $ageingT += (float) $p->total;
        }

        return view('reports.proposals', compact('from', 'to', 'rows', 'funnel', 'won', 'wonC', 'lost', 'decided', 'decidedC', 'byClient', 'buckets', 'ageingT', 'reasons', 'byReason', 'byCompetitor', 'lostT'));
    }

    /** Client profitability: cash received vs project costs per client. */
    public function clients(Request $r)
    {
        [$from, $to] = $this->period($r);
        $received = DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)
            ->select('client_id', DB::raw('SUM(amount) t'))->groupBy('client_id')->pluck('t', 'client_id');
        $costs = DB::table('expenses')->join('projects', 'projects.id', '=', 'expenses.project_id')
            ->where('expenses.status', 'approved')
            ->whereDate('expenses.spent_on', '>=', $from)->whereDate('expenses.spent_on', '<=', $to)
            ->select('projects.client_id', DB::raw('SUM(expenses.amount) t'))->groupBy('projects.client_id')->pluck('t', 'projects.client_id');
        $clients = DB::table('clients')->orderBy('company')->orderBy('name')->get(['id', 'name', 'company']);
        $rows = $clients->map(fn ($c) => [
            'client' => $c, 'received' => (float) ($received[$c->id] ?? 0), 'costs' => (float) ($costs[$c->id] ?? 0),
        ])->map(fn ($x) => $x + ['profit' => round($x['received'] - $x['costs'], 2)])
            ->sortByDesc('profit')->values();

        return view('reports.clients', compact('from', 'to', 'rows'));
    }

    /** Receivables ageing: open balances bucketed by days past due. */
    public function receivables(Request $r)
    {
        $rows = DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereIn('invoices.status', ['sent', 'partial', 'overdue'])
            ->select('invoices.number', 'invoices.due_date', 'invoices.total', 'invoices.paid_amount', 'invoices.writeoff_amount', 'clients.name', 'clients.company')
            ->orderBy('invoices.due_date')->get()
            ->map(fn ($i) => [...(array) $i, 'bal' => max(0, round((float) $i->total - (float) $i->paid_amount - (float) ($i->writeoff_amount ?? 0), 2))])
            ->filter(fn ($i) => $i['bal'] > 0)->values();
        $today = now()->toDateString();
        $buckets = ['Not due yet' => [], '0–30 days' => [], '31–60 days' => [], '61–90 days' => [], '90+ days' => []];
        foreach ($rows as $i) {
            $age = $i['due_date'] ? (int) now()->diffInDays($i['due_date'], true) * ($i['due_date'] < $today ? 1 : -1) : -1;
            $b = $age < 0 ? 'Not due yet' : ($age <= 30 ? '0–30 days' : ($age <= 60 ? '31–60 days' : ($age <= 90 ? '61–90 days' : '90+ days')));
            $buckets[$b][] = $i;
        }
        $totals = collect($buckets)->map(fn ($g) => round(collect($g)->sum('bal'), 2));

        return view('reports.receivables', compact('rows', 'buckets', 'totals'));
    }

    /** Profit & loss: cash revenue vs approved expenses by category. */
    public function pnl(Request $r)
    {
        $month = $r->input('month', now()->format('Y-m'));
        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();
        $revenue = (float) DB::table('payments')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->sum('amount');
        $byCat = DB::table('expenses')->where('status', 'approved')
            ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->select('category', DB::raw('COUNT(*) c'), DB::raw('SUM(amount) t'))->groupBy('category')->orderByDesc('t')->get();
        $costs = (float) $byCat->sum('t');
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $start->copy()->addMonth()->gt(now()->startOfMonth()) ? null : $start->copy()->addMonth()->format('Y-m');

        return view('reports.pnl', compact('month', 'from', 'revenue', 'byCat', 'costs', 'prev', 'next'));
    }

    protected function period(Request $r): array
    {
        return [$r->input('from', now()->startOfMonth()->toDateString()), $r->input('to', now()->toDateString())];
    }

    public function export(Request $r)
    {
        $type = $r->input('type', 'payments');
        // The client directory is sensitive — admins only.
        if ($type === 'clients' && ! auth()->user()->isAdmin()) {
            abort(403, 'Administrators only.');
        }
        $from = $r->input('from', now()->startOfMonth()->toDateString());
        $to = $r->input('to', now()->toDateString());
        $file = "report-{$type}-{$from}-to-{$to}.csv";
        $rows = match ($type) {
            'expenses' => DB::table('expenses')->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->orderBy('spent_on')->get(['number', 'spent_on as date', 'title', 'category', 'vendor', 'amount', 'status']),
            'invoices' => DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')->whereDate('invoices.issue_date', '>=', $from)->whereDate('invoices.issue_date', '<=', $to)->orderByDesc('invoices.id')->get(['invoices.number', 'clients.name as client', 'invoices.issue_date as date', 'invoices.status', 'invoices.total', 'invoices.paid_amount']),
            'clients' => DB::table('clients')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->orderBy('name')->get(['name', 'company', 'email', 'phone', 'gstin', 'state', 'status']),
            default => DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to)->orderBy('paid_on')->get(['paid_on as date', 'clients.name as client', 'invoices.number as invoice', 'payments.method', 'payments.reference', 'payments.amount']),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()));
            }
            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv']);
    }

    /**
     * GSTR-1 outward supplies: B2B per invoice-rate, B2C grouped,
     * HSN summary, and CDNR credit notes (tax split estimated pro-rata
     * from the linked invoice — credit_notes carries no tax breakup).
     * Only filed statuses count: drafts and cancelled never appear.
     */
    public function gstr1(Request $r)
    {
        $from = $r->input('from', now()->startOfMonth()->toDateString());
        $to = $r->input('to', now()->toDateString());
        $data = $this->gstr1Data($from, $to);

        return view('reports.gstr1', array_merge(compact('from', 'to'), $data));
    }

    public function gstr1Export(Request $r)
    {
        $type = $r->input('type', 'b2b');
        if (! in_array($type, ['b2b', 'b2c', 'hsn', 'cdnr'], true)) {
            abort(404);
        }
        $from = $r->input('from', now()->startOfMonth()->toDateString());
        $to = $r->input('to', now()->toDateString());
        $data = $this->gstr1Data($from, $to);
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $rows = match ($type) {
            'b2c' => collect($data['b2c'])->map(fn ($g) => [
                'Type' => $g['type'], 'Place of supply' => $g['pos'], 'Rate %' => $g['rate'],
                'Taxable' => $n($g['taxable']), 'CGST' => $n($g['cgst']), 'SGST' => $n($g['sgst']),
                'IGST' => $n($g['igst']), 'Docs' => $g['count'],
            ]),
            'hsn' => collect($data['hsn'])->map(fn ($h) => [
                'HSN' => $h['hsn'], 'UQC' => $h['uqc'], 'Qty' => $h['qty'],
                'Taxable' => $n($h['taxable']), 'CGST' => $n($h['cgst']), 'SGST' => $n($h['sgst']), 'IGST' => $n($h['igst']),
            ]),
            'cdnr' => collect($data['cdnr'])->map(fn ($c) => [
                'Note no' => $c['number'], 'Date' => $c['date'], 'Linked invoice' => $c['invoice'],
                'GSTIN' => $c['gstin'], 'Rate %' => $c['rate'], 'Taxable' => $n($c['taxable']),
                'CGST' => $n($c['cgst']), 'SGST' => $n($c['sgst']), 'IGST' => $n($c['igst']), 'Estimated' => 'yes',
            ]),
            default => collect($data['b2b'])->map(fn ($b) => [
                'GSTIN' => $b['gstin'], 'Receiver' => $b['name'], 'Invoice no' => $b['number'], 'Date' => $b['date'],
                'Place of supply' => $b['pos'], 'RCM' => $b['rcm'] ? 'yes' : 'no', 'Rate %' => $b['rate'],
                'Taxable' => $n($b['taxable']), 'CGST' => $n($b['cgst']), 'SGST' => $n($b['sgst']),
                'IGST' => $n($b['igst']), 'Invoice value' => $n($b['value']),
            ]),
        };

        return csv_response("gstr1-{$type}-{$from}-to-{$to}.csv", $rows);
    }

    /**
     * TDS register: client deductions on our invoices (receivable, 26AS) and
     * our deductions on vendor expenses (payable). FY picker, April–March.
     */
    public function tds(Request $r)
    {
        $fys = DB::table('tds_entries')->distinct()->orderByDesc('fy')->pluck('fy')->filter()->values()->all();
        $fy = $r->input('fy', fy());
        if (! in_array($fy, $fys, true)) {
            $fys[] = $fy;
            rsort($fys);
        }
        $data = $this->tdsData($fy);

        return view('reports.tds', array_merge(compact('fy', 'fys'), $data));
    }

    public function tdsExport(Request $r)
    {
        $fy = $r->input('fy', fy());
        $data = $this->tdsData($fy);
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $rows = collect($data['rows'])->map(fn ($e) => [
                'Kind' => $e['kind'] === 'receivable' ? 'TDS on income (26AS)' : 'TDS on expense',
                'Date' => substr((string) $e['date'], 0, 10), 'Section' => $e['section'], 'Rate %' => $e['rate'],
            'Deductee' => $e['party'], 'PAN' => $e['pan'], 'Reference' => $e['ref'],
            'Base amount' => $n($e['base']), 'TDS amount' => $n($e['amount']),
        ]);

        return csv_response("tds-register-{$fy}.csv", $rows);
    }

    protected function tdsData(string $fy): array
    {
        $rows = DB::table('tds_entries')->leftJoin('invoices', 'invoices.id', '=', 'tds_entries.invoice_id')
            ->leftJoin('clients', 'clients.id', '=', 'tds_entries.client_id')
            ->leftJoin('expenses', 'expenses.id', '=', 'tds_entries.expense_id')
            ->where('tds_entries.fy', $fy)->orderBy('deducted_on')->orderBy('tds_entries.id')
            ->get(['tds_entries.*', 'invoices.number as invoice', 'clients.company as company', 'clients.name as cname', 'clients.pan as pan', 'expenses.number as expense']);
        $out = [];
        $tot = ['rec' => 0.0, 'pay' => 0.0];
        foreach ($rows as $t) {
            $rec = $t->kind === 'receivable';
            $party = $rec ? ($t->company ?: $t->cname ?: '—') : ($t->vendor_name ?: '—');
            $ref = $t->invoice ?: ($t->expense ?: '—');
            $out[] = [
                'kind' => $t->kind, 'date' => substr((string) $t->deducted_on, 0, 10), 'section' => $t->section, 'rate' => $t->rate,
                'party' => $party, 'pan' => $rec ? ($t->pan ?: '—') : '—', 'ref' => $ref,
                'base' => (float) $t->base_amount, 'amount' => (float) $t->amount,
                'link' => $t->invoice_id ? route('invoices.show', $t->invoice_id) : null,
            ];
            $k = $rec ? 'rec' : 'pay';
            $tot[$k] = round($tot[$k] + (float) $t->amount, 2);
        }

        return ['rows' => $out, 'tot' => $tot];
    }

    /** Shared builder for the GSTR-1 page and its CSV exports. */    protected function gstr1Data(string $from, string $to): array
    {
        $filed = ['sent', 'partial', 'paid', 'overdue'];
        $invoices = Invoice::with(['items', 'client'])
            ->whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)
            ->whereIn('status', $filed)->orderBy('issue_date')->orderBy('id')->get();

        $b2b = [];
        $b2c = [];
        $hsn = [];
        $tot = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'docs' => 0];
        $add = function (&$t, $taxable, $cgst, $sgst, $igst) {
            $t['taxable'] = round($t['taxable'] + $taxable, 2);
            $t['cgst'] = round($t['cgst'] + $cgst, 2);
            $t['sgst'] = round($t['sgst'] + $sgst, 2);
            $t['igst'] = round($t['igst'] + $igst, 2);
        };
        foreach ($invoices as $inv) {
            $intra = $inv->isIntraState();
            $slabs = $inv->taxSlabs();
            $pos = $inv->place_of_supply ?: '—';
            $gstin = trim((string) ($inv->client->gstin ?? ''));
            if ($gstin !== '') {
                foreach ($slabs as $s) {
                    $cgst = $intra ? round($s['tax'] / 2, 2) : 0;
                    $sgst = $intra ? round($s['tax'] / 2, 2) : 0;
                    $igst = $intra ? 0 : $s['tax'];
                    $b2b[] = [
                        'number' => $inv->number, 'date' => $inv->issue_date->toDateString(),
                        'gstin' => $gstin, 'name' => $inv->client->company ?: $inv->client->name,
                        'pos' => $pos, 'rcm' => (bool) $inv->reverse_charge,
                        'rate' => $s['rate'], 'taxable' => $s['taxable'],
                        'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst, 'value' => $inv->total,
                    ];
                    $add($tot, $s['taxable'], $cgst, $sgst, $igst);
                }
                $tot['docs']++;
            } else {
                $type = (! $intra && (float) $inv->total > 100000) ? 'B2CL' : 'B2CS';
                $touched = [];
                foreach ($slabs as $s) {
                    $k = $type.'|'.$pos.'|'.Calculator::slabKey($s['rate']);
                    $b2c[$k] ??= ['type' => $type, 'pos' => $pos, 'rate' => $s['rate'], 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'count' => 0];
                    $cgst = $intra ? round($s['tax'] / 2, 2) : 0;
                    $sgst = $intra ? round($s['tax'] / 2, 2) : 0;
                    $igst = $intra ? 0 : $s['tax'];
                    $b2c[$k]['taxable'] = round($b2c[$k]['taxable'] + $s['taxable'], 2);
                    $b2c[$k]['cgst'] = round($b2c[$k]['cgst'] + $cgst, 2);
                    $b2c[$k]['sgst'] = round($b2c[$k]['sgst'] + $sgst, 2);
                    $b2c[$k]['igst'] = round($b2c[$k]['igst'] + $igst, 2);
                    $touched[$k] = true;
                    $add($tot, $s['taxable'], $cgst, $sgst, $igst);
                }
                // Count each B2C invoice once per group it touches.
                foreach (array_keys($touched) as $k) {
                    $b2c[$k]['count']++;
                }
                $tot['docs']++;
            }
            // HSN summary from pro-rata line taxables (discount-aware).
            $computed = Calculator::computeLines($inv->items, (float) ($inv->discount ?? 0), $inv->gst_rate);
            foreach ($computed['lines'] as $l) {
                $code = trim((string) ($l['item']->hsn_code ?? '')) !== '' ? $l['item']->hsn_code : '—';
                $hsn[$code] ??= ['hsn' => $code, 'uqc' => strtoupper($l['item']->unit ?? 'NOS'), 'qty' => 0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
                $hsn[$code]['qty'] += $l['item']->qty ?? 0;
                $hsn[$code]['taxable'] = round($hsn[$code]['taxable'] + $l['taxable'], 2);
                if ($intra) {
                    $hsn[$code]['cgst'] = round($hsn[$code]['cgst'] + $l['tax'] / 2, 2);
                    $hsn[$code]['sgst'] = round($hsn[$code]['sgst'] + $l['tax'] / 2, 2);
                } else {
                    $hsn[$code]['igst'] = round($hsn[$code]['igst'] + $l['tax'], 2);
                }
            }
        }
        ksort($b2c);
        ksort($hsn);

        // CDNR: credit notes have no tax breakup — split the note amount
        // pro-rata across the linked invoice's slabs, flagged estimated.
        $notes = DB::table('credit_notes')
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
                    ->orWhere(fn ($q2) => $q2->whereNull('date')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to));
            })->orderBy('id')->get();
        $linked = Invoice::with(['items', 'client'])->whereIn('id', $notes->pluck('invoice_id')->filter()->unique())->get()->keyBy('id');
        $cdnr = [];
        $noteTotals = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'count' => 0];
        foreach ($notes as $note) {
            $inv = $note->invoice_id ? ($linked[$note->invoice_id] ?? null) : null;
            $ndate = $note->date ?? substr((string) $note->created_at, 0, 10);
            $base = ['number' => $note->number, 'date' => $ndate, 'invoice' => $inv?->number ?? '—', 'gstin' => trim((string) ($inv?->client->gstin ?? ''))];
            if ($inv) {
                $slabs = $inv->taxSlabs();
                $slabTax = array_sum(array_column($slabs, 'taxable'));
                $intra = $inv->isIntraState();
                $left = (float) $note->amount;
                foreach ($slabs as $i => $s) {
                    $share = $slabTax > 0 ? $s['taxable'] / $slabTax : 0;
                    $taxable = $i === count($slabs) - 1 ? round($left, 2) : round((float) $note->amount * $share, 2);
                    $left -= $taxable;
                    $tax = round($taxable * $s['rate'] / 100, 2);
                    $cgst = $intra ? round($tax / 2, 2) : 0;
                    $sgst = $intra ? round($tax / 2, 2) : 0;
                    $igst = $intra ? 0 : $tax;
                    $cdnr[] = $base + ['rate' => $s['rate'], 'taxable' => $taxable, 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst];
                    $add($noteTotals, $taxable, $cgst, $sgst, $igst);
                }
                // Slab-less invoice (zero-tax doc): one estimated row.
                if (empty($slabs)) {
                    $cdnr[] = $base + ['rate' => null, 'taxable' => round((float) $note->amount, 2), 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
                    $add($noteTotals, (float) $note->amount, 0, 0, 0);
                }
            } else {
                $cdnr[] = $base + ['rate' => null, 'taxable' => round((float) $note->amount, 2), 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
                $add($noteTotals, (float) $note->amount, 0, 0, 0);
            }
            $noteTotals['count']++;
        }

        return ['b2b' => $b2b, 'b2c' => array_values($b2c), 'hsn' => array_values($hsn), 'cdnr' => $cdnr, 'tot' => $tot, 'noteTotals' => $noteTotals];
    }
}
