<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Attendance;
use Modules\Work\Leave;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = Leave::with('user')->orderByDesc('id');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        } elseif ($r->filled('user_id')) {
            $q->where('user_id', $r->user_id);
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        $users = $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
        $mine = Leave::balances($canAll && $r->filled('user_id') ? (int) $r->user_id : $me->id);

        return view('leaves.index', [
            'leaves' => $q->paginate(perPage())->withQueryString(),
            'users' => $users, 'canAll' => $canAll,
            'status' => $r->status, 'user_id' => $r->user_id,
            'balances' => $mine,
            'pending' => $canAll ? Leave::where('status', 'pending')->count() : 0,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'nullable|exists:users,id',
            'from_date' => 'required|date', 'to_date' => 'required|date|after_or_equal:from_date',
            'type' => 'required|in:sick,casual,earned,unpaid', 'reason' => 'nullable|string|max:500',
        ]);
        $me = auth()->user();
        $uid = (int) ($data['user_id'] ?? $me->id);
        if ($uid !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'You can only apply for yourself.');
        }
        $leave = Leave::create([
            'user_id' => $uid, 'from_date' => $data['from_date'], 'to_date' => $data['to_date'],
            'type' => $data['type'], 'reason' => $data['reason'] ?? null, 'status' => 'pending',
        ]);

        return back()->with('ok', 'Leave applied for '.$leave->days().' working day(s) — awaiting approval.');
    }

    /** Approve or reject (managers+). Approvals stamp attendance automatically. */
    public function decide(Request $r, Leave $leave)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers and admins decide leave.');
        }
        $data = $r->validate(['status' => 'required|in:approved,rejected']);
        $leave->update(['status' => $data['status'], 'decided_by' => $me->id, 'decided_at' => now()]);
        if ($data['status'] === 'approved') {
            // Past + today get stamped now; future days stamp when they arrive
            // (the nightly scheduler backfills approved leave daily).
            $d = $leave->from_date->copy();
            $today = now()->toDateString();
            while ($d->lte($leave->to_date)) {
                if ($d->toDateString() <= $today && ! $d->isWeekend()) {
                    Attendance::mark($leave->user_id, $d->toDateString(), 'leave', 'Approved '.Leave::types()[$leave->type].' leave');
                }
                $d->addDay();
            }
        }
        activity('leave-'.$data['status'], $leave, 'Leave '.$leave->from_date->toDateString().' → '.$leave->to_date->toDateString().' '.$data['status']);

        return back()->with('ok', 'Leave '.$data['status'].'.');
    }

    public function destroy(Leave $leave)
    {
        $me = auth()->user();
        if ($leave->user_id !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403);
        }
        if ($leave->status === 'approved') {
            return back()->withErrors(['leave' => 'Approved leave stays on record — ask a manager to reject it instead.']);
        }
        $leave->delete();

        return back()->with('ok', 'Leave request withdrawn.');
    }
}
