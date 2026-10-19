<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Shift;

class ShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    /** Weekly roster grid (this week + next), managers edit, everyone views. */
    public function index(Request $r)
    {
        $start = now()->startOfWeek();
        $weeks = [];
        foreach ([0, 1] as $w) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[] = $start->copy()->addWeeks($w)->addDays($i);
            }
            $weeks[] = $days;
        }
        $marks = Shift::whereDate('day', '>=', $weeks[0][0]->toDateString())
            ->whereDate('day', '<=', $weeks[1][6]->toDateString())
            ->get()->groupBy(fn ($s) => $s->user_id.'|'.substr((string) $s->day, 0, 10));
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $canEdit = auth()->user()->isAdmin() || auth()->user()->isManager();

        return view('shifts.index', compact('weeks', 'marks', 'users', 'canEdit'));
    }

    public function store(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers set the roster.');
        }
        $data = $r->validate([
            'user_id' => 'required|exists:users,id', 'day' => 'required|date',
            'label' => 'required|string|max:60', 'note' => 'nullable|string|max:255',
        ]);
        Shift::updateOrCreate(
            ['user_id' => $data['user_id'], 'day' => $data['day']],
            ['label' => $data['label'], 'note' => $data['note'] ?? null]
        );

        return back()->with('ok', 'Roster set.');
    }

    public function destroy(Shift $shift)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers set the roster.');
        }
        $shift->delete();

        return back()->with('ok', 'Roster entry removed.');
    }
}
