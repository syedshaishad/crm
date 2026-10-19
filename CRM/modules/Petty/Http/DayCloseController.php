<?php

namespace Modules\Petty\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Petty\DayClose;
use Modules\Petty\PettyEntry;

class DayCloseController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        $today = now()->toDateString();
        $closes = DayClose::with('closer')->orderByDesc('day')->limit(30)->get();
        $done = DayClose::where('day', $today)->first();
        $expected = PettyEntry::balance();
        $cashToday = (float) DB::table('payments')->where('method', 'cash')->whereDate('paid_on', $today)->sum('amount');
        $openInvoices = (int) DB::table('invoices')->whereIn('status', ['sent', 'partial', 'overdue'])->whereRaw('total > paid_amount')->count();
        $dueCheques = (int) DB::table('pdc_cheques')->where('status', 'held')->whereDate('cheque_date', '<=', $today)->count();

        return view('petty.close', compact('today', 'closes', 'done', 'expected', 'cashToday', 'openInvoices', 'dueCheques'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'cash_counted' => 'required|numeric|min:0|max:100000000',
            'checks' => 'nullable|array', 'checks.*' => 'nullable|in:on,1,true',
            'notes' => 'nullable|string|max:500',
        ]);
        $today = now()->toDateString();
        $checks = [];
        foreach (array_keys(DayClose::checklist()) as $k) {
            $checks[$k] = ! empty($data['checks'][$k]);
        }
        // Match by date (not datetime string) so re-closing updates, not dupes.
        $close = DayClose::whereDate('day', $today)->first() ?? new DayClose(['day' => $today]);
        $close->fill([
            'cash_counted' => $data['cash_counted'],
            'expected_petty' => PettyEntry::balance(),
            'cash_receipts' => (float) DB::table('payments')->where('method', 'cash')->whereDate('paid_on', $today)->sum('amount'),
            'checks' => $checks, 'notes' => $data['notes'] ?? null, 'closed_by' => auth()->id(),
        ]);
        $close->save();

        return back()->with('ok', 'Day closed.');
    }
}
