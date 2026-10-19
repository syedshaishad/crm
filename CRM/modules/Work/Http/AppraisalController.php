<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Appraisal;

class AppraisalController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = Appraisal::with(['user', 'reviewer'])->orderByDesc('id');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        } elseif ($r->filled('user_id')) {
            $q->where('user_id', $r->user_id);
        }
        $users = $canAll ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();

        return view('appraisals.index', [
            'rows' => $q->paginate(perPage())->withQueryString(),
            'users' => $users, 'canAll' => $canAll, 'user_id' => $r->user_id,
        ]);
    }

    public function store(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers and admins write appraisals.');
        }
        $data = $r->validate([
            'user_id' => 'required|exists:users,id',
            'period' => 'required|string|max:20',
            'rating' => 'required|integer|min:1|max:5',
            'strengths' => 'nullable|string', 'improvements' => 'nullable|string', 'goals' => 'nullable|string',
        ]);
        Appraisal::updateOrCreate(
            ['user_id' => $data['user_id'], 'period' => $data['period']],
            [...$data, 'reviewer_id' => $me->id]
        );

        return back()->with('ok', 'Appraisal saved.');
    }

    public function destroy(Appraisal $appraisal)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403);
        }
        $appraisal->delete();

        return back()->with('ok', 'Appraisal removed.');
    }
}
