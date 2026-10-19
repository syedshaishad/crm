<?php

namespace Modules\Work\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Reimbursement;

class ReimbursementController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index()
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = Reimbursement::with('user')->orderByDesc('spent_on');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        }

        return view('reimbursements.index', ['rows' => $q->paginate(perPage()), 'canAll' => $canAll]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'nullable|exists:users,id',
            'spent_on' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1', 'reason' => 'required|string|max:255',
        ]);
        $me = auth()->user();
        $uid = (int) ($data['user_id'] ?? $me->id);
        if ($uid !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'You can only claim your own expenses.');
        }
        Reimbursement::create(['user_id' => $uid, 'spent_on' => $data['spent_on'], 'amount' => $data['amount'], 'reason' => $data['reason'], 'status' => 'pending']);

        return back()->with('ok', 'Claim filed — a manager approves, payroll pays.');
    }

    public function decide(Request $r, Reimbursement $reimbursement)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers decide claims.');
        }
        $data = $r->validate(['status' => 'required|in:approved,rejected']);
        if ($reimbursement->payroll_id) {
            return back()->withErrors(['claim' => 'Already paid in a payroll run.']);
        }
        $reimbursement->update(['status' => $data['status']]);

        return back()->with('ok', 'Claim '.$data['status'].'.');
    }

    public function destroy(Reimbursement $reimbursement)
    {
        $me = auth()->user();
        if ($reimbursement->user_id !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403);
        }
        if ($reimbursement->payroll_id) {
            return back()->withErrors(['claim' => 'Already paid in a payroll run.']);
        }
        $reimbursement->delete();

        return back()->with('ok', 'Claim withdrawn.');
    }
}
