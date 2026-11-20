<?php

namespace Modules\Renewals\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Renewals\Renewal;
use Modules\Renewals\RenewalService;

class RenewalsController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }

    public function index(Request $r)
    {
        $status = in_array($r->input('status'), ['upcoming', 'drafted', 'won', 'lost', 'ignored'], true)
            ? $r->input('status') : 'upcoming';
        $rows = Renewal::with(['client', 'proposal'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('renewal_date')
            ->paginate(perPage())
            ->withQueryString();

        $all = Renewal::get(['status', 'amount', 'renewal_date']);
        $stats = [
            'upcoming' => $all->where('status', 'upcoming')->count(),
            'drafted' => $all->where('status', 'drafted')->count(),
            'won' => $all->where('status', 'won')->count(),
            'lost' => $all->where('status', 'lost')->count(),
            'value' => $all->whereIn('status', ['upcoming', 'drafted'])->sum('amount'),
        ];

        return view('renewals.index', compact('rows', 'status', 'stats', 'all'));
    }

    public function sync(RenewalService $service)
    {
        $counts = $service->sync();

        return back()->with('ok', 'Renewals refreshed from '.array_sum($counts).' source records.');
    }

    public function draft(Renewal $renewal, RenewalService $service)
    {
        try {
            $proposal = $service->draft($renewal);

            return redirect()->route('proposals.edit', $proposal)->with('ok', 'Draft renewal proposal created.');
        } catch (\Throwable $e) {
            return back()->withErrors(['renewal' => $e->getMessage()]);
        }
    }

    public function mark(Request $r, Renewal $renewal)
    {
        $data = $r->validate(['status' => 'required|in:upcoming,won,lost,ignored']);
        $renewal->update(['status' => $data['status']]);

        return back()->with('ok', 'Renewal marked '.e($data['status']).'.');
    }
}
