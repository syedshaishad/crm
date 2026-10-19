<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Bonus;

class BonusController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = Bonus::with('user')->orderByDesc('month');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        }

        return view('bonuses.index', [
            'rows' => $q->paginate(perPage()),
            'users' => $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'canAll' => $canAll,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m', 'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);
        Bonus::create(['user_id' => $data['user_id'], 'month' => $data['month'].'-01', 'amount' => $data['amount'], 'reason' => $data['reason'] ?? null, 'status' => 'pending']);

        return back()->with('ok', 'Bonus proposed — approve it to queue it for payroll.');
    }

    public function decide(Request $r, Bonus $bonus)
    {
        $data = $r->validate(['status' => 'required|in:approved,rejected']);
        if ($bonus->payroll_id) {
            return back()->withErrors(['bonus' => 'Already paid in a payroll run.']);
        }
        $bonus->update(['status' => $data['status']]);

        return back()->with('ok', 'Bonus '.$data['status'].'.');
    }

    public function destroy(Bonus $bonus)
    {
        if ($bonus->payroll_id) {
            return back()->withErrors(['bonus' => 'Already paid in a payroll run.']);
        }
        $bonus->delete();

        return back()->with('ok', 'Bonus removed.');
    }
}
