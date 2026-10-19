<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\StaffLoan;

class LoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        return view('loans.index', [
            'rows' => StaffLoan::with('user')->orderByDesc('id')->paginate(perPage()),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'required|exists:users,id',
            'principal' => 'required|numeric|min:1', 'emi' => 'required|numeric|min:1',
            'disbursed_on' => 'required|date', 'note' => 'nullable|string|max:255',
        ]);
        StaffLoan::create([...$data, 'status' => 'active']);

        return back()->with('ok', 'Loan recorded — EMI deducts automatically every payroll.');
    }

    public function destroy(StaffLoan $loan)
    {
        if ($loan->repaid() > 0) {
            return back()->withErrors(['loan' => 'Repayments exist — close it by finishing the EMIs instead.']);
        }
        $loan->delete();

        return back()->with('ok', 'Loan removed.');
    }
}
