<?php

namespace Modules\Expenses\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Numbering\NextNumber;
use Modules\Expenses\Expense;
use Modules\Expenses\RecurringExpenseProfile;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }
    public function index(Request $r)
    {
        $q = Expense::with('project')->orderByDesc('spent_on');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $s)->orWhere('vendor', 'like', $s)->orWhere('notes', 'like', $s));
        }
        if ($r->filled('category')) {
            $q->where('category', $r->category);
        }
        if ($r->filled('project_id')) {
            $q->where('project_id', $r->project_id);
        }
        if ($r->filled('status') && in_array($r->status, ['pending', 'approved', 'rejected'], true)) {
            $q->where('status', $r->status);
        }
        $pending = Expense::where('status', 'pending')->count();

        $base = Expense::query();
        $stats = [
            'total' => (clone $base)->count(),
            'pending' => $pending,
            'approved' => (float) (clone $base)->where('status', 'approved')->sum('amount'),
            'approved_count' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'month' => (float) (clone $base)->whereBetween('spent_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount'),
        ];

        return view('expenses.index', [
            'expenses' => $q->paginate(perPage())->withQueryString(),
            'q' => $r->q, 'category' => $r->category, 'project_id' => $r->project_id, 'status' => $r->status, 'pending' => $pending, 'stats' => $stats,
            'categories' => Expense::select('category')->distinct()->orderBy('category')->pluck('category'),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $r)
    {
        return view('expenses.form', [
            'expense' => new Expense(['project_id' => $r->project_id, 'category' => 'general']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'title' => 'required|string', 'category' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0', 'spent_on' => 'required|date',
            'vendor' => 'nullable|string', 'method' => 'nullable|string|max:20', 'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'tds_section' => 'nullable|string|max:20', 'tds_amount' => 'nullable|numeric|min:0',
            'receipt' => 'nullable|file|max:'.(int) ($s->get('uploads.receipt_max_mb', 10) * 1024),
        ]);
        if ($r->hasFile('receipt')) {
            if ($reason = upload_reject_reason($r->file('receipt'))) {
                return back()->withErrors(['receipt' => $reason])->withInput();
            }
            $data['receipt_path'] = $r->file('receipt')->store('receipts', 'public');
        }
        unset($data['receipt']);
        $prefix = $s->get('expense.prefix', 'EXP-');
        $data['number'] = NextNumber::take($prefix, 'expenses');
        $data['created_by'] = auth()->id();
        // Staff expenses wait for a manager; managers/admins self-approve.
        if (auth()->user()->isAdmin() || auth()->user()->isManager()) {
            $data['status'] = 'approved';
            $data['approved_by'] = auth()->id();
            $data['approved_on'] = now()->toDateString();
        } else {
            $data['status'] = 'pending';
        }
        $expense = Expense::create($data);
        $this->saveTds($expense, $r);

        return redirect()->route('expenses.index')->with('ok', 'Expense '.$data['number'].' saved.'.($expense->status === 'pending' ? ' Waiting for manager approval.' : ''));
    }

    public function edit(Expense $expense)
    {
        return view('expenses.form', ['expense' => $expense, 'projects' => Project::orderBy('name')->get(['id', 'name'])]);
    }

    public function show(Expense $expense)
    {
        $expense->load(['project.client', 'creator']);

        return view('expenses.show', compact('expense'));
    }

    public function update(Request $r, Expense $expense, SettingStore $s)
    {
        $data = $r->validate([
            'title' => 'required|string', 'category' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0', 'spent_on' => 'required|date',
            'vendor' => 'nullable|string', 'method' => 'nullable|string|max:20', 'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'tds_section' => 'nullable|string|max:20', 'tds_amount' => 'nullable|numeric|min:0',
            'receipt' => 'nullable|file|max:'.(int) ($s->get('uploads.receipt_max_mb', 10) * 1024),
        ]);
        if ($r->hasFile('receipt')) {
            if ($reason = upload_reject_reason($r->file('receipt'))) {
                return back()->withErrors(['receipt' => $reason])->withInput();
            }
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $r->file('receipt')->store('receipts', 'public');
        }
        unset($data['receipt']);
        $expense->update($data);
        $this->saveTds($expense, $r);

        return redirect()->route('expenses.index')->with('ok', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        DB::table('tds_entries')->where('expense_id', $expense->id)->where('kind', 'payable')->delete();
        $expense->delete(); // soft: receipt file stays so restore works

        return back()->with('ok', 'Expense moved to Trash.');
    }

    /** Manager decision on a staff claim (pending only, either way). */
    public function approve(Expense $expense)
    {
        $this->ensureReviewer();
        abort_unless($expense->status === 'pending', 400, 'Only pending expenses can be reviewed.');
        $expense->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_on' => now()->toDateString()]);
        activity('expense-approved', $expense, 'Expense '.$expense->number.' approved '.money($expense->amount));

        return back()->with('ok', 'Expense '.$expense->number.' approved — now in the books.');
    }

    public function reject(Request $r, Expense $expense)
    {
        $this->ensureReviewer();
        abort_unless($expense->status === 'pending', 400, 'Only pending expenses can be reviewed.');
        $data = $r->validate(['notes' => 'nullable|string|max:500']);
        $expense->update([
            'status' => 'rejected',
            'notes' => trim(($expense->notes ? $expense->notes."\n" : '').'Rejected '.now()->toDateString().($data['notes'] ? ': '.$data['notes'] : '.')),
        ]);

        return back()->with('ok', 'Expense '.$expense->number.' rejected — kept for the record.');
    }

    protected function ensureReviewer(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isAdmin() || $u->isManager()), 403, 'Managers only.');
    }

    /** Replace the vendor-TDS entry for an expense (or clear it). */
    protected function saveTds(Expense $expense, Request $r): void
    {
        DB::table('tds_entries')->where('expense_id', $expense->id)->where('kind', 'payable')->delete();
        $amt = round((float) $r->input('tds_amount', 0), 2);
        if (setting('tax.tds_enabled', '0') !== '1' || $amt <= 0 || ! $r->input('tds_section')) {
            return;
        }
        $sections = tds_sections();
        $code = strtoupper($r->input('tds_section'));
        DB::table('tds_entries')->insert([
            'kind' => 'payable', 'expense_id' => $expense->id, 'vendor_name' => $expense->vendor,
            'section' => $code, 'rate' => $sections[$code] ?? 0, 'base_amount' => round((float) $expense->amount, 2),
            'amount' => $amt, 'deducted_on' => $expense->spent_on, 'fy' => fy($expense->spent_on),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Recurring schedules: auto-book repeat spend on a cadence. */
    public function recurringIndex()
    {
        return view('expenses.recurring', ['profiles' => RecurringExpenseProfile::with('project')->orderByDesc('id')->paginate(perPage())]);
    }

    public function recurringStore(Request $r, Expense $expense)
    {
        $data = $r->validate([
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'next_run_on' => 'required|date|after_or_equal:today',
        ]);
        RecurringExpenseProfile::create([
            'title' => $expense->title, 'category' => $expense->category, 'amount' => $expense->amount,
            'vendor' => $expense->vendor, 'method' => $expense->method, 'project_id' => $expense->project_id,
            'notes' => $expense->notes, 'frequency' => $data['frequency'], 'next_run_on' => $data['next_run_on'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Recurring schedule created — first booking on '.d($data['next_run_on'], 'j M Y').'.');
    }

    public function recurringToggle(RecurringExpenseProfile $profile)
    {
        $profile->update(['is_active' => ! $profile->is_active]);

        return back()->with('ok', $profile->is_active ? 'Schedule resumed.' : 'Schedule paused.');
    }

    public function recurringDestroy(RecurringExpenseProfile $profile)
    {
        $profile->delete();

        return back()->with('ok', 'Recurring schedule deleted.');
    }
}

