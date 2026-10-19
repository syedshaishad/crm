<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Expenses\Expense;
use Modules\Work\Attendance;
use Modules\Work\Bonus;
use Modules\Work\Leave;
use Modules\Work\LoanRepayment;
use Modules\Work\OvertimeEntry;
use Modules\Work\Payroll;
use Modules\Work\Payslip;
use Modules\Work\Reimbursement;
use Modules\Work\SalaryAdvance;
use Modules\Work\SalaryStructure;
use Modules\Work\StaffLoan;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        return view('payroll.index', ['rows' => Payroll::orderByDesc('month')->paginate(perPage())]);
    }

    /** Start (or open) a draft run for a month. */
    public function run(Request $r)
    {
        $data = $r->validate(['month' => 'required|date_format:Y-m']);
        $month = $data['month'].'-01';
        $payroll = Payroll::whereDate('month', $month)->first() ?? Payroll::create(['month' => $month, 'status' => 'draft']);
        if ($payroll->status === 'finalized') {
            return redirect()->route('payroll.show', $payroll)->withErrors(['payroll' => 'That month is already finalized.']);
        }
        $this->buildDraft($payroll);

        return redirect()->route('payroll.show', $payroll)->with('ok', 'Draft run for '.$payroll->label().' ready — review, then finalize.');
    }

    public function show(Payroll $payroll)
    {
        $payroll->load('slips.user');

        return view('payroll.show', compact('payroll'));
    }

    public function destroy(Payroll $payroll)
    {
        if ($payroll->status === 'finalized') {
            return back()->withErrors(['payroll' => 'Finalized runs stay on record.']);
        }
        $this->releaseStamps($payroll);
        $payroll->slips()->delete();
        $payroll->delete();

        return redirect()->route('payroll.index')->with('ok', 'Draft run discarded.');
    }

    /**
     * (Re)build every payslip from live data. Idempotent: previous draft
     * stamps are released first so figures never double-count.
     */
    protected function buildDraft(Payroll $payroll): void
    {
        $this->releaseStamps($payroll);
        $payroll->slips()->delete();
        $month = $payroll->month->toDateString();
        $start = $payroll->month->copy()->startOfMonth()->toDateString();
        $end = $payroll->month->copy()->endOfMonth()->toDateString();
        $working = Payroll::workingDays($month);
        $otMult = (float) setting('payroll.overtime_rate', 1.5);
        $tdsPct = (float) setting('payroll.tds_percent', 10);
        $users = User::where('is_active', true)->orderBy('name')->get();
        foreach ($users as $u) {
            $struct = SalaryStructure::for($u->id, $end);
            if (! $struct || $struct->gross() <= 0) {
                continue;
            }
            $gross = $struct->gross();
            // Paid days: everything counts except absences and unpaid leave.
            $absent = (int) Attendance::where('user_id', $u->id)->where('status', 'absent')->whereDate('day', '>=', $start)->whereDate('day', '<=', $end)->count();
            $unpaidLeave = (int) Leave::where('user_id', $u->id)->where('type', 'unpaid')->where('status', 'approved')->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get()->sum->days();
            $paid = max(0, min($working, $working - $absent - $unpaidLeave));
            $earned = $working > 0 ? round($gross * $paid / $working, 2) : 0;
            // Overtime at a multiple of the hourly cost.
            $otMins = (int) OvertimeEntry::where('user_id', $u->id)->where('status', 'approved')->whereDate('day', '>=', $start)->whereDate('day', '<=', $end)->sum('minutes');
            $hourly = $working > 0 ? $gross / ($working * 8) : 0;
            $otPay = round($otMins / 60 * $hourly * $otMult, 2);
            // Approved bonuses + reimbursements waiting for a run.
            $bonusRows = Bonus::where('user_id', $u->id)->where('status', 'approved')->whereNull('payroll_id')->get();
            $bonus = round((float) $bonusRows->sum('amount'), 2);
            $reimRows = Reimbursement::where('user_id', $u->id)->where('status', 'approved')->whereNull('payroll_id')->get();
            $reim = round((float) $reimRows->sum('amount'), 2);
            // Advance recovery: clear what's owed, never below zero net.
            $advBal = SalaryAdvance::balance($u->id);
            $advCut = max(0, min($advBal, $earned + $otPay + $bonus));
            // Loan EMIs: oldest active loan first, close when repaid.
            $loanCut = 0;
            $loanStamps = [];
            foreach (StaffLoan::where('user_id', $u->id)->where('status', 'active')->orderBy('id')->get() as $loan) {
                $left = $loan->outstanding();
                if ($left <= 0) {
                    $loan->update(['status' => 'closed']);

                    continue;
                }
                $cut = min((float) $loan->emi, $left, max(0, $earned + $otPay + $bonus - $advCut - $loanCut));
                if ($cut > 0) {
                    $loanCut = round($loanCut + $cut, 2);
                    $loanStamps[] = $loan->id;
                }
            }
            // Salary TDS (192): flat % on earnings above the monthly standard slice.
            $taxable = max(0, $earned + $otPay + $bonus - 4167);
            $tds = round($taxable * $tdsPct / 100, 2);
            $net = round($earned + $otPay + $bonus + $reim - $advCut - $loanCut - $tds, 2);
            $slip = $payroll->slips()->create([
                'user_id' => $u->id, 'working_days' => $working, 'paid_days' => $paid,
                'gross_monthly' => $gross, 'gross_earned' => $earned, 'overtime_pay' => $otPay,
                'bonus' => $bonus, 'reimbursements' => $reim, 'advance_cut' => $advCut,
                'loan_cut' => $loanCut, 'tds' => $tds, 'net' => max(0, $net),
            ]);
            $bonusRows->each->update(['payroll_id' => $payroll->id]);
            $reimRows->each->update(['payroll_id' => $payroll->id]);
            foreach ($loanStamps as $lid) {
                LoanRepayment::create(['loan_id' => $lid, 'payroll_id' => $payroll->id, 'amount' => 0, 'paid_on' => $end]);
            }
            // Stamp the exact loan cuts onto their repayment rows.
            $this->distributeLoanCut($payroll, $u->id, $loanCut);
        }
    }

    /** Split this run's loan cut across the stamped loans, oldest first. */
    protected function distributeLoanCut(Payroll $payroll, int $userId, float $total): void
    {
        $left = $total;
        $rows = LoanRepayment::where('payroll_id', $payroll->id)->whereHas('loan', fn ($q) => $q->where('user_id', $userId))->orderBy('id')->get();
        foreach ($rows as $row) {
            $loan = StaffLoan::find($row->loan_id);
            if (! $loan || $left <= 0) {
                continue;
            }
            $give = min($left, (float) $loan->emi, $loan->outstanding() + (float) $row->amount);
            $row->update(['amount' => round($give, 2)]);
            $left = round($left - $give, 2);
        }
    }

    /** Release draft stamps so a rebuild starts clean. */
    protected function releaseStamps(Payroll $payroll): void
    {
        Bonus::where('payroll_id', $payroll->id)->update(['payroll_id' => null]);
        Reimbursement::where('payroll_id', $payroll->id)->update(['payroll_id' => null]);
        LoanRepayment::where('payroll_id', $payroll->id)->delete();
    }

    /**
     * Finalize: locks the run and posts it to the books — one approved
     * salary expense, advance recoveries, and the 192 TDS payable.
     */
    public function finalize(Payroll $payroll)
    {
        if ($payroll->status === 'finalized') {
            return back()->withErrors(['payroll' => 'Already finalized.']);
        }
        if ($payroll->slips()->count() === 0) {
            return back()->withErrors(['payroll' => 'Nothing to finalize — no staff with a salary structure.']);
        }
        $admin = auth()->id();
        $end = $payroll->month->copy()->endOfMonth()->toDateString();
        $slips = $payroll->slips()->with('user')->get();
        $totalNet = round($slips->sum('net'), 2);
        $totalTds = round($slips->sum('tds'), 2);
        Expense::create([
            'number' => \Modules\Core\Numbering\NextNumber::take(setting('expense.prefix', 'EXP-'), 'expenses'),
            'title' => 'Salaries — '.$payroll->label(), 'category' => 'salary', 'amount' => $totalNet,
            'spent_on' => $end, 'vendor' => 'Payroll', 'method' => 'bank',
            'status' => 'approved', 'approved_by' => $admin, 'approved_on' => $end, 'created_by' => $admin,
        ]);
        foreach ($slips as $s) {
            if ($s->advance_cut > 0) {
                SalaryAdvance::create(['user_id' => $s->user_id, 'kind' => 'recovery', 'amount' => $s->advance_cut, 'dated' => $end, 'note' => 'Payroll '.$payroll->label(), 'created_by' => $admin]);
            }
            if ($s->tds > 0) {
                DB::table('tds_entries')->insert([
                    'kind' => 'payable', 'section' => '192', 'rate' => (float) setting('payroll.tds_percent', 10),
                    'base_amount' => $s->gross_earned + $s->overtime_pay + $s->bonus, 'amount' => $s->tds,
                    'deducted_on' => $end, 'fy' => fy($end), 'notes' => 'Salary — '.$s->user->name.' ('.$payroll->label().')',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach (StaffLoan::where('user_id', $s->user_id)->where('status', 'active')->get() as $loan) {
                if ($loan->outstanding() <= 0) {
                    $loan->update(['status' => 'closed']);
                }
            }
        }
        $payroll->update(['status' => 'finalized', 'finalized_by' => $admin, 'finalized_at' => now()]);
        activity('payroll-finalized', $payroll, 'Payroll '.$payroll->label().' finalized: '.money($totalNet).' net, '.money($totalTds).' TDS.');

        return back()->with('ok', 'Payroll finalized — '.money($totalNet).' booked to salaries.');
    }

    /** Printable payslip for one staffer. */
    public function slip(Payroll $payroll, \Modules\Work\Payslip $payslip)
    {
        abort_unless((int) $payslip->payroll_id === (int) $payroll->id, 404);
        $payslip->load('user');

        return view('payroll.slip', compact('payroll', 'payslip'));
    }
}
