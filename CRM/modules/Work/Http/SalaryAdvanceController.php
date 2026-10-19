<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\SalaryAdvance;

class SalaryAdvanceController extends Controller
{
    public function __construct()
    {
        // Listing is self-scoped for staff; writes abort for non-managers inside.
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = SalaryAdvance::with('user')->orderByDesc('dated')->orderByDesc('id');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        } elseif ($r->filled('user_id')) {
            $q->where('user_id', (int) $r->user_id);
        }
        $rows = $q->paginate(perPage())->withQueryString();
        $balances = [];
        $who = $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect([$me]);
        foreach ($who as $u) {
            $b = SalaryAdvance::balance((int) $u->id);
            if ($b != 0) {
                $balances[] = ['user' => $u, 'balance' => $b];
            }
        }

        return view('salary.index', compact('rows', 'balances', 'who', 'canAll'));
    }

    public function store(Request $r)
    {
        $me = auth()->user();
        abort_unless($me->isAdmin() || $me->isManager(), 403, 'Managers only.');
        $data = $r->validate([
            'user_id' => 'required|exists:users,id',
            'kind' => 'required|in:advance,recovery',
            'amount' => 'required|numeric|min:0.01|max:100000000',
            'dated' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:255',
        ]);
        if ($data['kind'] === 'recovery' && (float) $data['amount'] > SalaryAdvance::balance((int) $data['user_id'])) {
            return back()->withErrors(['amount' => 'Recovery exceeds the '.money(SalaryAdvance::balance((int) $data['user_id'])).' owed.'])->withInput();
        }
        SalaryAdvance::create($data + ['created_by' => $me->id]);

        return back()->with('ok', ucfirst($data['kind']).' of '.money($data['amount']).' recorded.');
    }

    public function destroy(SalaryAdvance $advance)
    {
        $me = auth()->user();
        abort_unless($me->isAdmin() || $me->isManager(), 403, 'Managers only.');
        $advance->delete();

        return back()->with('ok', 'Entry removed.');
    }
}
