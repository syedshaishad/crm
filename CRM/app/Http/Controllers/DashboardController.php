<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:dashboard');
    }
    public function index()
    {
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

        // Cashflow: last 6 months money in vs money out (works on MySQL + SQLite).
        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = now()->subMonths($m);
            $months[] = [
                'label' => $d->format('M'),
                'revenue' => (float) DB::table('payments')->whereYear('paid_on', $d->year)->whereMonth('paid_on', $d->month)->sum('amount'),
                'expenses' => (float) DB::table('expenses')->where('status','approved')->whereYear('spent_on', $d->year)->whereMonth('spent_on', $d->month)->sum('amount'),
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
        $calY = (int) now()->format('Y');
        $calM = (int) now()->format('n');
        $calStart = (int) date('N', mktime(0, 0, 0, $calM, 1, $calY));
        $calDays = (int) date('t', mktime(0, 0, 0, $calM, 1, $calY));
        $dueMap = [];
        foreach (DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->whereYear('due_date', $calY)->whereMonth('due_date', $calM)->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['tasks'] = $r->c;
        }
        foreach (DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled'])->whereNotNull('due_date')->whereYear('due_date', $calY)->whereMonth('due_date', $calM)->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['invoices'] = $r->c;
        }

        // Week activity: collections per day, last 7 days.
        $week = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $day = now()->subDays($wd);
            $week[] = [
                'label' => $day->format('D'),
                'value' => (float) DB::table('payments')->whereDate('paid_on', $day->toDateString())->sum('amount'),
            ];
        }
        $weekMax = max(1, collect($week)->max('value'));

        // 7-day spark series behind each KPI card: new pipeline value,
        // invoiced value, and daily profit (collections minus approved spend).
        // Precomputed SVG paths (viewBox 0 0 120 36) so the view stays dumb.
        $series7 = function (string $table, string $dateCol, string $sumCol, string $extraWhere = '') {
            $vals = [];
            for ($wd = 6; $wd >= 0; $wd--) {
                $day = now()->subDays($wd)->toDateString();
                $q = DB::table($table)->whereDate($dateCol, $day);
                if ($extraWhere !== '') $q->whereRaw($extraWhere);
                $vals[] = (float) $q->sum($sumCol);
            }

            return $vals;
        };
        $pipeWeek = $series7('proposals', 'created_at', 'total');
        $billWeek = $series7('invoices', 'issue_date', 'total');
        $expWeek = $series7('expenses', 'spent_on', 'amount', "status = 'approved'");
        $payWeek = array_column($week, 'value');
        $profitWeek = array_map(fn ($p, $e) => round($p - $e, 2), $payWeek, $expWeek);
        // Tasks finished per day (last 7 days) for the weekly panel switch.
        $doneWeek = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $day = now()->subDays($wd)->toDateString();
            $doneWeek[] = (int) DB::table('tasks')->whereIn('status', ['done', 'completed'])->whereDate('updated_at', $day)->count();
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
        $mtd = (float) DB::table('payments')->whereYear('paid_on', now()->year)->whereMonth('paid_on', now()->month)->sum('amount');
        $avg3 = 0;
        for ($m = 1; $m <= 3; $m++) {
            $d = now()->subMonths($m);
            $avg3 += (float) DB::table('payments')->whereYear('paid_on', $d->year)->whereMonth('paid_on', $d->month)->sum('amount');
        }
        $avg3 = round($avg3 / 3, 2);
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
        $m0 = now()->startOfMonth()->toDateString();
        $pm0 = now()->subMonth()->startOfMonth()->toDateString();
        $pm1 = now()->subMonth()->endOfMonth()->toDateString();
        $dealSum = function ($status, $from, $to = null) {
            $q = DB::table('proposals')->where('status', $status)->whereDate('updated_at', '>=', $from);
            if ($to) $q->whereDate('updated_at', '<=', $to);
            $r = $q->selectRaw('COUNT(*) c, COALESCE(SUM(total),0) t')->first();
            return ['c' => (int) ($r->c ?? 0), 't' => (float) ($r->t ?? 0)];
        };
        $chg = function ($cur, $prev) {
            if ($prev > 0) return round(($cur - $prev) / $prev * 100, 1);
            return $cur > 0 ? 100 : 0;
        };
        $wonW = $dealSum('accepted', $w0);
        $wonPW = $dealSum('accepted', $pw0, $pw1);
        $lostW = $dealSum('declined', $w0);
        $lostPW = $dealSum('declined', $pw0, $pw1);
        $wonM = $dealSum('accepted', $m0);
        $lostM = $dealSum('declined', $m0);
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
        $deals['inbound'] = (int) DB::table('leads')->whereDate('created_at', '>=', $m0)->whereIn('source', $inSources)->count();
        $deals['outbound'] = (int) DB::table('leads')->whereDate('created_at', '>=', $m0)->where('source', 'coldcall')->count();
        // Weekly performance: collections, new pipeline, tasks finished.
        $weekPay = (float) DB::table('payments')->whereDate('paid_on', '>=', $w0)->sum('amount');
        $weekPayC = (int) DB::table('payments')->whereDate('paid_on', '>=', $w0)->count();
        $weekDone = (int) DB::table('tasks')->whereIn('status', ['done', 'completed'])->whereDate('updated_at', '>=', $w0)->count();
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
            $ref = $spot->valid_until ?? $spot->updated_at;
            $spot->day = $ref ? \Carbon\Carbon::parse($ref)->format('d') : '—';
            $spot->mon = $ref ? \Carbon\Carbon::parse($ref)->format('M') : '';
        }

        // Monthly won/lost series for the deals area chart (last 6 months) +
        // precomputed SVG paths (viewBox 0 0 300 120), so the view stays dumb.
        $dealMonths = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = now()->subMonths($m);
            $dealMonths[] = [
                'label' => $d->format('M'),
                'won' => (float) DB::table('proposals')->where('status', 'accepted')->whereYear('updated_at', $d->year)->whereMonth('updated_at', $d->month)->sum('total'),
                'lost' => (float) DB::table('proposals')->where('status', 'declined')->whereYear('updated_at', $d->year)->whereMonth('updated_at', $d->month)->sum('total'),
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

        return view('dashboard', compact('stats', 'recentInvoices', 'recentTasks', 'overdue', 'dueSoon', 'months', 'maxBar', 'billed', 'collected', 'outstanding', 'overdueTotal', 'collectionRate', 'pipeline', 'pipelineTotal', 'funnel', 'funnelTotal', 'focusTasks', 'calY', 'calM', 'calStart', 'calDays', 'dueMap', 'week', 'weekMax', 'sparks', 'payWeek', 'pipeWeek', 'billWeek', 'doneWeek', 'pipeClients', 'recentActivity', 'lowStock', 'target', 'mtd', 'targetPct', 'forecast', 'forecastMax', 'openPipe', 'deals', 'perf', 'spot', 'openDeals', 'dealRows', 'dealChart'));
    }
    public function money()
    {
        // Cashflow: last 6 months money in vs money out (works on MySQL + SQLite).
        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $d = now()->subMonths($m);
            $months[] = [
                'label' => $d->format('M'),
                'revenue' => (float) DB::table('payments')->whereYear('paid_on', $d->year)->whereMonth('paid_on', $d->month)->sum('amount'),
                'expenses' => (float) DB::table('expenses')->where('status','approved')->whereYear('spent_on', $d->year)->whereMonth('spent_on', $d->month)->sum('amount'),
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
        $mtd = (float) DB::table('payments')->whereYear('paid_on', now()->year)->whereMonth('paid_on', now()->month)->sum('amount');
        $avg3 = 0;
        for ($m = 1; $m <= 3; $m++) {
            $d = now()->subMonths($m);
            $avg3 += (float) DB::table('payments')->whereYear('paid_on', $d->year)->whereMonth('paid_on', $d->month)->sum('amount');
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
        foreach (DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->whereYear('due_date', $calY)->whereMonth('due_date', $calM)->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['tasks'] = $r->c;
        }
        foreach (DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled'])->whereNotNull('due_date')->whereYear('due_date', $calY)->whereMonth('due_date', $calM)->selectRaw('date(due_date) d, COUNT(*) c')->groupBy('d')->get() as $r) {
            $dueMap[substr($r->d, 0, 10)]['invoices'] = $r->c;
        }

        // Week activity: collections per day, last 7 days.
        $week = [];
        for ($wd = 6; $wd >= 0; $wd--) {
            $day = now()->subDays($wd);
            $week[] = [
                'label' => $day->format('D'),
                'value' => (float) DB::table('payments')->whereDate('paid_on', $day->toDateString())->sum('amount'),
            ];
        }
        $weekMax = max(1, collect($week)->max('value'));

        $recentTasks = DB::table('tasks')->orderByDesc('id')->limit(8)->get();
        $dueSoon = DB::table('tasks')->whereNotIn('status', ['done'])->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(7)->toDateString())->orderBy('due_date')->limit(5)->get();

        return view('work', compact('focusTasks', 'calY', 'calM', 'calStart', 'calDays', 'dueMap', 'week', 'weekMax', 'recentTasks', 'dueSoon'));
    }
}
