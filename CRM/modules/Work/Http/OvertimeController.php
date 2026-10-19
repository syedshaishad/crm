<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\OvertimeEntry;

class OvertimeController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = OvertimeEntry::with('user')->orderByDesc('day');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        } elseif ($r->filled('user_id')) {
            $q->where('user_id', $r->user_id);
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('overtime.index', [
            'rows' => $q->paginate(perPage())->withQueryString(),
            'users' => $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'canAll' => $canAll, 'status' => $r->status, 'user_id' => $r->user_id,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'nullable|exists:users,id',
            'day' => 'required|date|before_or_equal:today',
            'minutes' => 'required|integer|min:15|max:1440',
            'note' => 'nullable|string|max:255',
        ]);
        $me = auth()->user();
        $uid = (int) ($data['user_id'] ?? $me->id);
        if ($uid !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'You can only log your own overtime.');
        }
        OvertimeEntry::create(['user_id' => $uid, 'day' => $data['day'], 'minutes' => $data['minutes'], 'note' => $data['note'] ?? null, 'status' => 'pending']);

        return back()->with('ok', 'Overtime logged — awaiting approval.');
    }

    public function decide(Request $r, OvertimeEntry $entry)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers decide overtime.');
        }
        $data = $r->validate(['status' => 'required|in:approved,rejected']);
        $entry->update(['status' => $data['status'], 'decided_by' => $me->id]);

        return back()->with('ok', 'Overtime '.$data['status'].'.');
    }

    public function destroy(OvertimeEntry $entry)
    {
        $me = auth()->user();
        if ($entry->user_id !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403);
        }
        if ($entry->status === 'approved') {
            return back()->withErrors(['overtime' => 'Approved overtime stays on record.']);
        }
        $entry->delete();

        return back()->with('ok', 'Overtime entry withdrawn.');
    }
}
