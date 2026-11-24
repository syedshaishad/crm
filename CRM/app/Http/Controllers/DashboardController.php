<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Modules\Expenses\RecurringExpenseProfile;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:dashboard');
    }
    public function index()
    {
        // Selected reporting month (defaults to the current month, never future).
        $selMonth = (string) request('month', '');
        $ref = now()->startOfMonth();
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selMonth)) {
            try {
                $cand = \Carbon\Carbon::createFromFormat('Y-m-d', $selMonth.'-01')->startOfMonth();
                if ($cand->gte(now()->startOfMonth()->subMonths(23)) && $cand->lte(now()->startOfMonth())) $ref = $cand;
            } catch (\Throwable $e) {
            }
        }
        $selMonth = $ref->format('Y-m');
        $monthOptions = [];
        for ($mi = 23; $mi >= 0; $mi--) {
            $md = now()->startOfMonth()->subMonths($mi);
            $monthOptions[] = [
                'value' => $md->format('Y-m'),
                'label' => $md->format('M j').' - '.$md->copy()->endOfMonth()->format('M j, Y'),
            ];
        }

        $stats = [
            'clients' => DB::table('clients')->count(),
            'projects' => DB::table('projects')->count(),
            'open_tasks' => DB::table('tasks')->whereNotIn('status', ['done', 'completed'])->count(),
            'unpaid_invoices' => DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled'])->count(),
            'revenue' => DB::table('payments')->sum('amount'),
            'expenses' => DB::table('expenses')->where('status','approved')->sum('amount'),
        ];
        $recentInvoices = DB::table('invoices')->orderByDesc('id')->limit(5)->get();
        $recentTasks = DB::table('tasks')->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')->orderByDesc('tasks.id')->limit(5)->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'projects.name as project']);
        $overdue = DB::table('invoices')->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')->where('invoices.status', 'overdue')->orderBy('due_date')->limit(5)->get(['invoices.*', 'clients.name as client']);
        $dueSoon = DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(7)->toDateString())->orderBy('due_date')->limit(5)->get();

        // Cashflow: last 6 months money in vs money out. One grouped query per
        // table instead of one query per month (cross-platform date expression).
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? fn ($col) => "strftime('%Y-%m', {$col})"
            : fn ($col) => "DATE_FORMAT({$col}, '%Y-%m')";
        $dayExpr = DB::getDriverName() === 'sqlite'
            ? fn ($col) => "strftime('%Y-%m-%d', {$col})"
            : fn ($col) => "DATE_FORMAT({$col}, '%Y-%m-%d')";
        $sixFrom = $ref->copy()->subMonths(5)->startOfMonth()->toDateString();
        $sixTo = $ref->copy()->endOfMonth()->toDateString();
        $revByMonth = DB::table('payments')->whereBetween('paid_on', [$sixFrom, $sixTo])
            ->selectRaw($monthExpr('paid_on').' m, SUM(amount) t')->groupBy('m')->pluck('t', 'm');
        $expByMonth = DB::table('expenses')->where('status', 'approved')->whereBetween('spent_on', [$sixFrom, $sixTo])
            ->selectRaw($monthExpr('spent_on').' m, SUM(amount) t')->groupBy('m')->pluck('t', 'm');
        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = $ref->copy()->subMonths($m);
            $key = $d->format('Y-m');
            $months[] = [
                'label' => $d->format('M'),
                'revenue' => (float) ($revByMonth[$key] ?? 0),
                'expenses' => (float) ($expByMonth[$key] ?? 0),
            ];
        }
        $maxBar = max(1, collect($months)->flatMap(fn ($x) => [$x['revenue'], $x['expenses']])->max());

        // Money position.
        $billed = (float) DB::table('invoices')->whereNotIn('status', ['cancelled'])->sum('total');
        $collected = (float) DB::table('payments')->sum('amount');
        $outstanding = max(0, $billed - $collected);
        $overdueTotal = (float) DB::table('invoices')->where('status', 'overdue')->selectRaw('SUM(total - paid_amount - COALESCE(writeoff_amount,0)) as t')->value('t');
        $collectionRate = $billed > 0 ? round($collected / $billed * 100) : 100;

        // Invoice pipeline by status.
        $pipeline = DB::table('invoices')->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(total) t'))->groupBy('status')->get();
        $pipelineTotal = max(1, $pipeline->sum('t'));

        // Collection funnel for the hero strip.
        $funnel = [
            ['label' => 'Collected', 'value' => $collected, 'color' => '#4ade80'],
            ['label' => 'Overdue', 'value' => (float) $overdueTotal, 'color' => '#f87171'],
            ['label' => 'Pending', 'value' => max(0, $outstanding - (float) $overdueTotal), 'color' => '#e8b90f'],
        ];
        $funnelTotal = max(1, $billed);

        // Today's focus: most urgent open tasks.
        $focusTasks = DB::table('tasks')->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereNotIn('tasks.status', ['done'])->orderByRaw('tasks.due_date IS NULL, tasks.due_date')->limit(5)
            ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'projects.name as project']);

        // Mini month calendar with due dots.
        $calY = (int) $ref->format('Y');
        $calM = (int) $ref->format('n');
        $calStart = (int) date('N', mktime(0, 0, 0, $calM, 1, $calY));
        $calDays = (int) date('t', mktime(0, 0, 0, $calM, 1, $calY));
        $dueMap = [];
        $calFrom = sprintf('%04d-%02d-01', $calY, $calM);
        $calTo = date('Y-m-t', mktime(0, 0, 0, $calM, 1, $calY));
        foreach (DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->whereBetween('due_date', [$calFrom, $calTo])->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['tasks'] = $r->c;
        }
        foreach (DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled'])->whereNotNull('due_date')->whereBetween('due_date', [$calFrom, $calTo])->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['invoices'] = $r->c;
        }

        // Week activity: collections per day, last 7 days (one query).
        $weekFrom = now()->subDays(6)->toDateString();
        $weekTo = now()->toDateString();
        $payByDay = DB::table('payments')->whereBetween('paid_on', [$weekFrom, $weekTo])
            ->selectRaw($dayExpr('paid_on').' d, SUM(amount) t')->groupBy('d')->pluck('t', 'd');
        $week = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $day = now()->subDays($wd);
            $week[] = [
                'label' => $day->format('D'),
                'value' => (float) ($payByDay[$day->toDateString()] ?? 0),
            ];
        }
        $weekMax = max(1, collect($week)->max('value'));

        // 7-day spark series behind each KPI card: new pipeline value,
        // invoiced value, and daily profit (collections minus approved spend).
        // Precomputed SVG paths (viewBox 0 0 120 36) so the view stays dumb.
        $series7 = function (string $table, string $dateCol, string $sumCol, ?string $status = null) use ($dayExpr, $weekFrom, $weekTo) {
            $isDateTime = in_array($dateCol, ['created_at', 'updated_at'], true);
            $from = $isDateTime ? $weekFrom.' 00:00:00' : $weekFrom;
            $to = $isDateTime ? $weekTo.' 23:59:59' : $weekTo;
            $q = DB::table($table)->whereBetween($dateCol, [$from, $to]);
            if ($status) $q->where('status', $status);
            $byDay = $q->selectRaw($dayExpr($dateCol).' d, SUM('.$sumCol.') t')->groupBy('d')->pluck('t', 'd');
            $vals = [];
            for ($wd = 6; $wd >= 0; $wd--) {
                $vals[] = (float) ($byDay[now()->subDays($wd)->toDateString()] ?? 0);
            }

            return $vals;
        };
        $pipeWeek = $series7('proposals', 'created_at', 'total');
        $billWeek = $series7('invoices', 'issue_date', 'total');
        $expWeek = $series7('expenses', 'spent_on', 'amount', 'approved');
        $payWeek = array_column($week, 'value');
        $profitWeek = array_map(fn ($p, $e) => round($p - $e, 2), $payWeek, $expWeek);
        // Tasks finished per day (last 7 days) for the weekly panel switch.
        $doneByDay = DB::table('tasks')->whereIn('status', ['done', 'completed'])
            ->whereBetween('updated_at', [$weekFrom.' 00:00:00', $weekTo.' 23:59:59'])
            ->selectRaw($dayExpr('updated_at').' d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        $doneWeek = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $doneWeek[] = (int) ($doneByDay[now()->subDays($wd)->toDateString()] ?? 0);
        }
        $sparkPath = function (array $vals) {
            $n = count($vals);
            if ($n < 2) return ['line' => '', 'area' => ''];
            $mx = max($vals);
            $mn = min($vals);
            if ($mx == 0 && $mn == 0) return ['line' => '', 'area' => ''];
            $span = ($mx - $mn) > 0 ? ($mx - $mn) : 1;
            $pts = [];
            foreach (array_values($vals) as $i => $v) {
                $pts[] = round($i * (120 / ($n - 1)), 1).','.round(33 - (($v - $mn) / $span) * 28, 1);
            }
            $line = 'M'.implode(' L', $pts);

            return ['line' => $line, 'area' => $line.' L120,34 L0,34 Z'];
        };
        $sparks = [
            'rev' => $sparkPath($payWeek),
            'pipe' => $sparkPath($pipeWeek),
            'bill' => $sparkPath($billWeek),
            'profit' => $sparkPath($profitWeek),
        ];

        $recentActivity = Activity::with('user')->orderByDesc('id')->limit(8)->get();

        // Forward look: company target vs this month, 3-month collection
        // average projected forward, and open pipeline coverage.
        $target = max(0, (float) setting('company.monthly_target', 0));
        $mtd = (float) ($months[5]['revenue'] ?? 0);
        $avg3 = round(((float) ($months[2]['revenue'] ?? 0) + (float) ($months[3]['revenue'] ?? 0) + (float) ($months[4]['revenue'] ?? 0)) / 3, 2);
        $forecast = [];
        for ($m = 1; $m <= 3; $m++) {
            $forecast[] = ['label' => now()->addMonths($m)->format('M'), 'value' => $avg3];
        }
        $forecastMax = max(1, $avg3, $mtd, $target);
        $openPipe = (float) DB::table('proposals')->where('status', 'sent')->sum('total');
        $targetPct = $target > 0 ? min(100, round($mtd / $target * 100)) : 0;

        // Low stock: tracked goods at or under their alert level.
        $lowStock = \Modules\Products\Product::withSum(['stockEntries as stock_in' => fn ($x) => $x->where('type', 'in')], 'qty')
            ->withSum(['stockEntries as stock_out' => fn ($x) => $x->where('type', 'out')], 'qty')
            ->withSum(['stockEntries as stock_adj' => fn ($x) => $x->where('type', 'adjustment')], 'qty')
            ->where('status', 'active')->whereNotNull('low_stock_at')->orderBy('name')->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'unit' => $p->unit, 'onHand' => round((float) ($p->opening_stock ?? 0) + (float) ($p->stock_in ?? 0) - (float) ($p->stock_out ?? 0) + (float) ($p->stock_adj ?? 0), 2), 'at' => (float) $p->low_stock_at])
            ->filter(fn ($s) => $s['onHand'] <= $s['at'])->values();

        // Deals closed (analytics overview): proposals won/lost this week vs
        // last week, month summary vs previous month, and win rate.
        $w0 = now()->startOfWeek()->toDateString();
        $pw0 = now()->subWeek()->startOfWeek()->toDateString();
        $pw1 = now()->subWeek()->endOfWeek()->toDateString();
        $m0 = $ref->copy()->startOfMonth()->toDateString();
        $m1 = $ref->copy()->endOfMonth()->toDateString();
        $pm0 = $ref->copy()->subMonth()->startOfMonth()->toDateString();
        $pm1 = $ref->copy()->subMonth()->endOfMonth()->toDateString();
        $proposalRows = DB::table('proposals')->select('status', 'total', 'updated_at')
            ->where('updated_at', '>=', min($pw0, $pm0).' 00:00:00')->get();
        $dealSum = function ($status, $from, $to = null) use ($proposalRows) {
            $c = 0; $t = 0.0;
            foreach ($proposalRows as $row) {
                if ($row->status !== $status) continue;
                $day = substr((string) $row->updated_at, 0, 10);
                if ($day < $from || ($to && $day > $to)) continue;
                $c++; $t += (float) $row->total;
            }
            return ['c' => $c, 't' => $t];
        };
        $chg = function ($cur, $prev) {
            if ($prev > 0) return round(($cur - $prev) / $prev * 100, 1);
            return $cur > 0 ? 100 : 0;
        };
        $wonW = $dealSum('accepted', $w0);
        $wonPW = $dealSum('accepted', $pw0, $pw1);
        $lostW = $dealSum('declined', $w0);
        $lostPW = $dealSum('declined', $pw0, $pw1);
        $wonM = $dealSum('accepted', $m0, $m1);
        $lostM = $dealSum('declined', $m0, $m1);
        $wonPM = $dealSum('accepted', $pm0, $pm1);
        $deals = [
            'wonW' => $wonW, 'lostW' => $lostW,
            'wonChg' => $chg($wonW['t'], $wonPW['t']),
            'lostChg' => $chg($lostW['t'], $lostPW['t']),
            'wonM' => $wonM, 'wonPM' => $wonPM,
            'rate' => ($wonM['c'] + $lostM['c']) > 0 ? round($wonM['c'] / ($wonM['c'] + $lostM['c']) * 100, 1) : null,
        ];
        // Pipeline created this week + inbound/outbound lead split this month.
        $pipeW = DB::table('proposals')->whereDate('created_at', '>=', $w0)->selectRaw('COUNT(*) c, COALESCE(SUM(total),0) t')->first();
        $deals['pipeW'] = ['c' => (int) ($pipeW->c ?? 0), 't' => (float) ($pipeW->t ?? 0)];
        $inSources = ['website', 'google', 'indiamart', 'justdial', 'referral', 'walkin', 'instagram', 'facebook', 'whatsapp'];
        $deals['inbound'] = (int) DB::table('leads')->whereDate('created_at', '>=', $m0)->whereDate('created_at', '<=', $m1)->whereIn('source', $inSources)->count();
        $deals['outbound'] = (int) DB::table('leads')->whereDate('created_at', '>=', $m0)->whereDate('created_at', '<=', $m1)->where('source', 'coldcall')->count();
        // Weekly performance: collections, new pipeline, tasks finished.
        $weekPay = (float) $payByDay->sum();
        $weekPayC = (int) DB::table('payments')->where('paid_on', '>=', $w0)->count();
        $weekDone = (int) $doneByDay->sum();
        $perf = ['pay' => $weekPay, 'payC' => $weekPayC, 'done' => $weekDone,
            'payShare' => $mtd > 0 ? round($weekPay / $mtd * 100, 1) : ($weekPay > 0 ? 100 : 0)];
        // Spotlight: biggest open proposal + top open deals for the table.
        $spot = DB::table('proposals')->leftJoin('clients', 'clients.id', '=', 'proposals.client_id')
            ->where('proposals.status', 'sent')->orderByDesc('proposals.total')->limit(1)
            ->first(['proposals.id', 'proposals.number', 'proposals.total', 'proposals.valid_until', 'proposals.updated_at', 'clients.name as client']);
        $openDeals = (int) DB::table('proposals')->where('status', 'sent')->count();
        // Open pipeline grouped by client (top 5 by value) for the dashboard panel.
        $pipeClients = DB::table('proposals')->leftJoin('clients', 'clients.id', '=', 'proposals.client_id')
            ->where('proposals.status', 'sent')
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.id as client_id, clients.name as client, COUNT(*) c, COALESCE(SUM(proposals.total),0) t, MIN(proposals.valid_until) as valid_until, MAX(proposals.number) as number, MAX(proposals.id) as id')
            ->orderByDesc('t')->limit(5)->get()
            ->map(function ($d) use ($openPipe) {
                $d->share = $openPipe > 0 ? round($d->t / $openPipe * 100, 1) : 0;
                $d->initial = strtoupper(mb_substr($d->client ?? '?', 0, 1));
                $d->gap = null;
                $d->gapTone = 'l';
                if ($d->valid_until) {
                    $days = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($d->valid_until)->startOfDay(), false);
                    if ($days < 0) { $d->gap = 'Expired'; $d->gapTone = 'r'; }
                    elseif ($days <= 7) { $d->gap = $days.'d left'; $d->gapTone = 'w'; }
                    else { $d->gap = 'On track'; $d->gapTone = 'l'; }
                }

                return $d;
            });
        $dealRows = DB::table('proposals')->leftJoin('clients', 'clients.id', '=', 'proposals.client_id')
            ->where('proposals.status', 'sent')->orderByDesc('proposals.total')->limit(5)
            ->get(['proposals.id', 'proposals.number', 'proposals.total', 'proposals.valid_until', 'clients.name as client'])
            ->map(function ($d) {
                $d->initial = strtoupper(mb_substr($d->client ?? $d->number ?? '?', 0, 1));
                $d->gap = null;
                $d->gapTone = 'b-grey';
                if ($d->valid_until) {
                    $days = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($d->valid_until)->startOfDay(), false);
                    if ($days < 0) { $d->gap = 'Expired'; $d->gapTone = 'b-red'; }
                    elseif ($days <= 7) { $d->gap = $days.'d left'; $d->gapTone = 'b-amber'; }
                    else { $d->gap = 'On track'; $d->gapTone = 'b-green'; }
                }
                return $d;
            });
        if ($spot) {
            $spot->initial = strtoupper(mb_substr($spot->client ?? $spot->number ?? '?', 0, 1));
            $spotDate = $spot->valid_until ?? $spot->updated_at;
            $spot->day = $spotDate ? \Carbon\Carbon::parse($spotDate)->format('d') : '—';
            $spot->mon = $spotDate ? \Carbon\Carbon::parse($spotDate)->format('M') : '';
        }

        // Monthly won/lost series for the deals area chart (last 6 months) +
        // precomputed SVG paths (viewBox 0 0 300 120), so the view stays dumb.
        // Two grouped queries, not one pair per month.
        $dealFrom = $ref->copy()->subMonths(5)->startOfMonth()->toDateTimeString();
        $dealTo = $ref->copy()->endOfMonth()->toDateTimeString();
        $wonByMonth = DB::table('proposals')->where('status', 'accepted')->whereBetween('updated_at', [$dealFrom, $dealTo])
            ->selectRaw($monthExpr('updated_at').' m, SUM(total) t')->groupBy('m')->pluck('t', 'm');
        $lostByMonth = DB::table('proposals')->where('status', 'declined')->whereBetween('updated_at', [$dealFrom, $dealTo])
            ->selectRaw($monthExpr('updated_at').' m, SUM(total) t')->groupBy('m')->pluck('t', 'm');
        $dealMonths = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = $ref->copy()->subMonths($m);
            $key = $d->format('Y-m');
            $dealMonths[] = [
                'label' => $d->format('M'),
                'won' => (float) ($wonByMonth[$key] ?? 0),
                'lost' => (float) ($lostByMonth[$key] ?? 0),
            ];
        }
        $dmx = max(1, collect($dealMonths)->flatMap(fn ($x) => [$x['won'], $x['lost']])->max());
        $dxx = function ($i) { return round($i * (300 / 5), 1); };
        $dxy = function ($v) use ($dmx) { return round(110 - ($v / $dmx) * 100, 1); };
        $wonPts = [];
        $lostPts = [];
        foreach ($dealMonths as $i => $dm) {
            $wonPts[] = $dxx($i).','.$dxy($dm['won']);
            $lostPts[] = $dxx($i).','.$dxy($dm['lost']);
        }
        $dealChart = [
            'months' => $dealMonths,
            'wonLine' => 'M'.implode(' L', $wonPts),
            'wonArea' => 'M'.implode(' L', $wonPts).' L300,110 L0,110 Z',
            'lostLine' => 'M'.implode(' L', $lostPts),
            'dxx' => $dxx,
            'dxy' => $dxy,
        ];

        return view('dashboard', compact('stats', 'recentInvoices', 'recentTasks', 'overdue', 'dueSoon', 'months', 'maxBar', 'billed', 'collected', 'outstanding', 'overdueTotal', 'collectionRate', 'pipeline', 'pipelineTotal', 'funnel', 'funnelTotal', 'focusTasks', 'calY', 'calM', 'calStart', 'calDays', 'dueMap', 'week', 'weekMax', 'sparks', 'payWeek', 'pipeWeek', 'billWeek', 'doneWeek', 'pipeClients', 'recentActivity', 'lowStock', 'target', 'mtd', 'targetPct', 'forecast', 'forecastMax', 'openPipe', 'deals', 'perf', 'spot', 'openDeals', 'dealRows', 'dealChart', 'ref', 'selMonth', 'monthOptions'));
    }
    /**
     * Forward-looking cash position. Everything here is derived from records
     * the app already keeps: open invoice balances for money in, active
     * recurring expense profiles for money out.
     */
    public function cashflow()
    {
        $today = now()->startOfDay();
        $start = $today->copy()->startOfWeek();
        $end = $start->copy()->addWeeks(13)->subDay();

        $weeks = [];
        for ($i = 0; $i < 13; $i++) {
            $from = $start->copy()->addWeeks($i);
            $weeks[] = [
                'from' => $from->toDateString(),
                'to' => $from->copy()->addDays(6)->toDateString(),
                'label' => $from->format('d M'),
                'in' => 0.0, 'out' => 0.0, 'net' => 0.0, 'cum' => 0.0,
            ];
        }
        $weekIndex = function ($date) use ($weeks) {
            $d = is_string($date) ? $date : $date->toDateString();
            foreach ($weeks as $i => $w) {
                if ($d >= $w['from'] && $d <= $w['to']) return $i;
            }
            return null;
        };

        $buckets = [
            'Not due yet' => 0.0, '0–30 days' => 0.0, '31–60 days' => 0.0,
            '61–90 days' => 0.0, '90+ days' => 0.0,
        ];
        $bucketCounts = array_fill_keys(array_keys($buckets), 0);
        $open = [];
        $outstanding = 0.0;
        $overdueTotal = 0.0;
        $dueWeek = 0.0;

        $rows = DB::table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereNotIn('invoices.status', ['draft', 'paid', 'cancelled'])
            ->whereNotNull('invoices.due_date')
            ->select('invoices.id', 'invoices.number', 'invoices.due_date', 'invoices.total',
                'invoices.paid_amount', 'invoices.writeoff_amount', 'invoices.status',
                'clients.name as client')
            ->get();

        foreach ($rows as $inv) {
            $bal = max(0, round((float) $inv->total - (float) $inv->paid_amount - (float) ($inv->writeoff_amount ?? 0), 2));
            if ($bal <= 0) continue;
            $due = \Carbon\Carbon::parse($inv->due_date)->startOfDay();
            $daysOverdue = max(0, (int) $due->diffInDays($today, false));
            $open[] = [
                'id' => $inv->id, 'number' => $inv->number,
                'client' => $inv->client ?: '—', 'due_date' => $due->toDateString(),
                'balance' => $bal, 'days_overdue' => $daysOverdue, 'status' => $inv->status,
            ];
            $outstanding += $bal;
            if ($daysOverdue > 0) $overdueTotal += $bal;
            if ($due->gte($today) && $due->lte($today->copy()->addDays(7))) $dueWeek += $bal;

            $key = $daysOverdue === 0 ? 'Not due yet'
                : ($daysOverdue <= 30 ? '0–30 days'
                : ($daysOverdue <= 60 ? '31–60 days'
                : ($daysOverdue <= 90 ? '61–90 days' : '90+ days')));
            $buckets[$key] += $bal;
            $bucketCounts[$key]++;
            $wi = $weekIndex($due);
            if ($wi !== null) $weeks[$wi]['in'] += $bal;
        }

        $advance = function (\Carbon\Carbon $date, ?string $frequency) {
            switch (strtolower((string) $frequency)) {
                case 'daily': return $date->copy()->addDay();
                case 'weekly': return $date->copy()->addWeek();
                case 'quarterly': return $date->copy()->addMonthsNoOverflow(3);
                case 'yearly': case 'annually': return $date->copy()->addYearNoOverflow();
                default: return $date->copy()->addMonthNoOverflow();
            }
        };
        foreach (RecurringExpenseProfile::where('is_active', true)->get() as $profile) {
            if (! $profile->next_run_on || (float) $profile->amount <= 0) continue;
            $date = \Carbon\Carbon::parse($profile->next_run_on)->startOfDay();
            for ($guard = 0; $date->lte($end) && $guard < 60; $guard++) {
                if ($date->gte($start)) {
                    $wi = $weekIndex($date);
                    if ($wi !== null) $weeks[$wi]['out'] += (float) $profile->amount;
                }
                $date = $advance($date, $profile->frequency);
            }
        }

        $cumulative = 0.0;
        foreach ($weeks as &$w) {
            $w['net'] = $w['in'] - $w['out'];
            $cumulative += $w['net'];
            $w['cum'] = $cumulative;
        }
        unset($w);
        $maxFlow = max(1.0, (float) collect($weeks)->max(function ($w) { return max($w['in'], $w['out']); }));
        $maxCum = max(1.0, (float) collect($weeks)->max(function ($w) { return abs($w['cum']); }));

        $cutoff = $today->copy()->addDays(7)->toDateString();
        $chase = collect($open)
            ->filter(function ($x) use ($cutoff) { return $x['days_overdue'] > 0 || $x['due_date'] <= $cutoff; })
            ->sort(function ($a, $b) {
                if ($a['days_overdue'] !== $b['days_overdue']) return $b['days_overdue'] <=> $a['days_overdue'];
                return $b['balance'] <=> $a['balance'];
            })
            ->take(15)->values();

        return view('cashflow', compact('weeks', 'buckets', 'bucketCounts', 'chase',
            'outstanding', 'overdueTotal', 'dueWeek', 'maxFlow', 'maxCum', 'start', 'end'));
    }

    public function money()
    {
        // Cashflow: last 6 months money in vs money out. Grouped queries keep
        // this to three round trips and stay index-friendly on MySQL + SQLite.
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? fn ($col) => "strftime('%Y-%m', {$col})"
            : fn ($col) => "DATE_FORMAT({$col}, '%Y-%m')";
        $sixFrom = now()->subMonths(5)->startOfMonth()->toDateString();
        $sixTo = now()->endOfMonth()->toDateString();
        $revByMonth = DB::table('payments')->whereBetween('paid_on', [$sixFrom, $sixTo])
            ->selectRaw($monthExpr('paid_on').' m, SUM(amount) t')->groupBy('m')->pluck('t', 'm');
        $expByMonth = DB::table('expenses')->where('status', 'approved')->whereBetween('spent_on', [$sixFrom, $sixTo])
            ->selectRaw($monthExpr('spent_on').' m, SUM(amount) t')->groupBy('m')->pluck('t', 'm');
        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = now()->subMonths($m);
            $key = $d->format('Y-m');
            $months[] = [
                'label' => $d->format('M'),
                'revenue' => (float) ($revByMonth[$key] ?? 0),
                'expenses' => (float) ($expByMonth[$key] ?? 0),
            ];
        }
        $maxBar = max(1, collect($months)->flatMap(fn ($x) => [$x['revenue'], $x['expenses']])->max());

        // Money position.
        $billed = (float) DB::table('invoices')->whereNotIn('status', ['cancelled'])->sum('total');
        $collected = (float) DB::table('payments')->sum('amount');
        $outstanding = max(0, $billed - $collected);
        $overdueTotal = (float) DB::table('invoices')->where('status', 'overdue')->selectRaw('SUM(total - paid_amount - COALESCE(writeoff_amount,0)) as t')->value('t');
        $collectionRate = $billed > 0 ? round($collected / $billed * 100) : 100;

        // Invoice pipeline by status.
        $pipeline = DB::table('invoices')->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(total) t'))->groupBy('status')->get();
        $pipelineTotal = max(1, $pipeline->sum('t'));

        // Forward look: company target vs this month, 3-month collection
        // average projected forward, and open pipeline coverage.
        $target = max(0, (float) setting('company.monthly_target', 0));
        // MTD is a true partial month, so it gets its own narrow range query;
        // the 3-month average reuses the grouped series above.
        $mtd = (float) DB::table('payments')
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->sum('amount');
        $avg3 = 0;
        for ($m = 1; $m <= 3; $m++) {
            $avg3 += (float) ($revByMonth[now()->subMonths($m)->format('Y-m')] ?? 0);
        }
        $avg3 = round($avg3 / 3, 2);
        $forecast = [];
        for ($m = 1; $m <= 3; $m++) {
            $forecast[] = ['label' => now()->addMonths($m)->format('M'), 'value' => $avg3];
        }
        $forecastMax = max(1, $avg3, $mtd, $target);
        $openPipe = (float) DB::table('proposals')->where('status', 'sent')->sum('total');
        $targetPct = $target > 0 ? min(100, round($mtd / $target * 100)) : 0;

        $recentInvoices = DB::table('invoices')->orderByDesc('id')->limit(5)->get();
        $overdue = DB::table('invoices')->where('status', 'overdue')->orderBy('due_date')->limit(5)->get();

        return view('money', compact('months', 'maxBar', 'billed', 'collected', 'outstanding', 'overdueTotal', 'collectionRate', 'pipeline', 'pipelineTotal', 'target', 'mtd', 'targetPct', 'forecast', 'forecastMax', 'openPipe', 'recentInvoices', 'overdue'));
    }
    public function work()
    {
        $dayExpr = DB::getDriverName() === 'sqlite'
            ? fn ($col) => "strftime('%Y-%m-%d', {$col})"
            : fn ($col) => "DATE_FORMAT({$col}, '%Y-%m-%d')";

        // Today's focus: most urgent open tasks.
        $focusTasks = DB::table('tasks')->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereNotIn('tasks.status', ['done'])->orderByRaw('tasks.due_date IS NULL, tasks.due_date')->limit(5)
            ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'projects.name as project']);

        // Mini month calendar with due dots.
        $calY = (int) now()->format('Y');
        $calM = (int) now()->format('n');
        $calStart = (int) date('N', mktime(0, 0, 0, $calM, 1, $calY));
        $calDays = (int) date('t', mktime(0, 0, 0, $calM, 1, $calY));
        $dueMap = [];
        $calFrom = sprintf('%04d-%02d-01', $calY, $calM);
        $calTo = date('Y-m-t', mktime(0, 0, 0, $calM, 1, $calY));
        foreach (DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->whereBetween('due_date', [$calFrom, $calTo])->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['tasks'] = $r->c;
        }
        foreach (DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled'])->whereNotNull('due_date')->whereBetween('due_date', [$calFrom, $calTo])->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['invoices'] = $r->c;
        }

        // Week activity: collections per day, last 7 days (one query).
        $weekFrom = now()->subDays(6)->toDateString();
        $weekTo = now()->toDateString();
        $payByDay = DB::table('payments')->whereBetween('paid_on', [$weekFrom, $weekTo])
            ->selectRaw($dayExpr('paid_on').' d, SUM(amount) t')->groupBy('d')->pluck('t', 'd');
        $week = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $day = now()->subDays($wd);
            $week[] = [
                'label' => $day->format('D'),
                'value' => (float) ($payByDay[$day->toDateString()] ?? 0),
            ];
        }
        $weekMax = max(1, collect($week)->max('value'));

        $recentTasks = DB::table('tasks')->orderByDesc('id')->limit(8)->get();
        $dueSoon = DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(7)->toDateString())->orderBy('due_date')->limit(5)->get();

        return view('work', compact('focusTasks', 'calY', 'calM', 'calStart', 'calDays', 'dueMap', 'week', 'weekMax', 'recentTasks', 'dueSoon'));
    }
}
