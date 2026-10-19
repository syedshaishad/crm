<?php

namespace Modules\Petty\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Petty\PettyEntry;

class PettyEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
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
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();

        $opening = PettyEntry::balanceOn($start->copy()->subDay()->toDateString());
        $rows = PettyEntry::with('creator')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)
            ->orderBy('entry_date')->orderBy('id')->get();
        $bal = $opening;
        $rows = $rows->map(function ($e) use (&$bal) {
            $bal = round($bal + ($e->kind === 'in' ? $e->amount : -$e->amount), 2);
            $e->running = $bal;

            return $e;
        });
        $in = $rows->where('kind', 'in')->sum('amount');
        $out = $rows->where('kind', 'out')->sum('amount');
        $prev = $start->copy()->subMonth()->format('Y-m');
        $next = $start->copy()->addMonth()->gt(now()->startOfMonth()) ? null : $start->copy()->addMonth()->format('Y-m');

        return view('petty.index', compact('month', 'from', 'to', 'rows', 'opening', 'in', 'out', 'prev', 'next') + ['balance' => PettyEntry::balance()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'entry_date' => 'required|date|before_or_equal:today',
            'kind' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01|max:10000000',
            'note' => 'nullable|string|max:255',
        ]);
        // A cash book never goes negative — not even backdated.
        if ($data['kind'] === 'out' && PettyEntry::balanceOn($data['entry_date']) < (float) $data['amount']) {
            return back()->withErrors(['amount' => 'Only '.money(PettyEntry::balanceOn($data['entry_date'])).' in hand on that date. Add cash first.'])->withInput();
        }
        PettyEntry::create($data + ['created_by' => auth()->id()]);

        return back()->with('ok', ($data['kind'] === 'in' ? 'Cash added. ' : 'Spend recorded. ').'Balance '.money(PettyEntry::balance()).'.');
    }

    public function destroy(PettyEntry $pettyEntry)
    {
        // Deleting an "in" must not strand later spends below zero.
        if ($pettyEntry->kind === 'in') {
            $without = PettyEntry::balance() - (float) $pettyEntry->amount;
            if ($without < 0) {
                return back()->withErrors(['petty' => 'Deleting this would drive the book negative. Delete the spends first.']);
            }
        }
        $pettyEntry->delete();

        return back()->with('ok', 'Entry moved to Trash.');
    }
}
