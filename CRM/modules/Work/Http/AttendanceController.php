<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Attendance;
use Modules\Work\Holiday;
use Modules\Work\Leave;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $month = $r->input('month', now()->format('Y-m'));
        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $uid = ($canAll && $r->filled('user_id')) ? (int) $r->user_id : $me->id;
        $marks = Attendance::where('user_id', $uid)
            ->whereDate('day', '>=', $start->toDateString())->whereDate('day', '<=', $start->copy()->endOfMonth()->toDateString())
            ->pluck('status', 'day');
        // SQLite returns date objects keyed oddly — normalize to Y-m-d strings.
        $marks = collect($marks)->mapWithKeys(fn ($s, $d) => [substr((string) $d, 0, 10) => $s]);
        $notes = Attendance::where('user_id', $uid)
            ->whereDate('day', '>=', $start->toDateString())->whereDate('day', '<=', $start->copy()->endOfMonth()->toDateString())
            ->pluck('note', 'day');
        $notes = collect($notes)->mapWithKeys(fn ($s, $d) => [substr((string) $d, 0, 10) => $s]);
        $summary = ['present' => 0, 'half' => 0, 'leave' => 0, 'absent' => 0];
        foreach ($marks as $s) {
            if (isset($summary[$s])) {
                $summary[$s]++;
            }
        }
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $start->copy()->addMonth()->gt(now()->startOfMonth()) ? null : $start->copy()->addMonth()->format('Y-m');
        $users = $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
        $todayMark = $marks->get(now()->toDateString());
        $holidays = Holiday::whereDate('day', '>=', $start->toDateString())->whereDate('day', '<=', $start->copy()->endOfMonth()->toDateString())->pluck('name', 'day');
        $holidays = collect($holidays)->mapWithKeys(fn ($s, $d) => [substr((string) $d, 0, 10) => $s]);
        $balances = Leave::balances($uid, (int) $start->format('Y'));

        return view('attendance.index', compact('month', 'start', 'marks', 'notes', 'summary', 'prev', 'next', 'users', 'uid', 'canAll', 'todayMark', 'holidays', 'balances'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'nullable|exists:users,id',
            'day' => 'required|date|before_or_equal:today',
            'status' => 'required|in:present,half,leave,absent',
            'note' => 'nullable|string|max:255',
        ]);
        $me = auth()->user();
        $uid = (int) ($data['user_id'] ?? $me->id);
        if ($uid !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'You can only mark your own attendance.');
        }
        Attendance::mark($uid, $data['day'], $data['status'], $data['note'] ?? null);

        return back()->with('ok', 'Attendance marked.');
    }
}
