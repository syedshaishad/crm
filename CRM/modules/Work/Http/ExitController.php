<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Asset;
use Modules\Work\ExitRecord;
use Modules\Work\SalaryAdvance;
use Modules\Work\StaffLoan;

class ExitController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        return view('exits.index', [
            'rows' => ExitRecord::with('user')->orderByDesc('id')->paginate(perPage()),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'required|exists:users,id|unique:exits,user_id',
            'resigned_on' => 'nullable|date', 'last_day' => 'nullable|date|after_or_equal:resigned_on',
            'reason' => 'nullable|string|max:500',
        ]);
        ExitRecord::create([...$data, 'status' => 'pending']);

        return back()->with('ok', 'Exit opened — clear dues and assets, then complete it.');
    }

    /**
     * Complete an exit: blocks on uncleared money or unreturned assets,
     * then deactivates the login and stamps the exit date.
     */
    public function complete(Request $r, ExitRecord $exit)
    {
        $data = $r->validate(['clearance' => 'nullable|string|max:1000']);
        $uid = $exit->user_id;
        $blockers = [];
        if (SalaryAdvance::balance($uid) > 0) {
            $blockers[] = 'salary advance '.money(SalaryAdvance::balance($uid)).' still owed';
        }
        foreach (StaffLoan::where('user_id', $uid)->where('status', 'active')->get() as $loan) {
            if ($loan->outstanding() > 0) {
                $blockers[] = 'loan '.money($loan->outstanding()).' outstanding';
            }
        }
        $assets = Asset::where('assigned_to', $uid)->get(['name']);
        if ($assets->isNotEmpty()) {
            $blockers[] = 'assets held: '.$assets->pluck('name')->implode(', ');
        }
        if ($blockers) {
            return back()->withErrors(['exit' => 'Cannot complete — '.implode('; ', $blockers).'.'])->withInput();
        }
        $exit->update(['status' => 'completed', 'clearance' => $data['clearance'] ?? null]);
        $user = User::find($uid);
        if ($user) {
            $user->update(['is_active' => false, 'exit_date' => $exit->last_day ?? now()->toDateString()]);
        }
        activity('exit-completed', $exit, 'Exit completed for '.($user->name ?? 'staff'));

        return back()->with('ok', 'Exit completed — login deactivated.');
    }

    public function destroy(ExitRecord $exit)
    {
        if ($exit->status === 'completed') {
            return back()->withErrors(['exit' => 'Completed exits stay on record.']);
        }
        $exit->delete();

        return back()->with('ok', 'Exit withdrawn.');
    }
}
