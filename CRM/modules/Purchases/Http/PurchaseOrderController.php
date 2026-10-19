<?php

namespace Modules\Purchases\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Numbering\NextNumber;
use Modules\Expenses\Expense;
use Modules\Projects\Project;
use Modules\Purchases\PurchaseOrder;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index(Request $r)
    {
        $q = PurchaseOrder::orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('vendor', 'like', $s));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('purchase-orders.index', ['orders' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status]);
    }

    public function create()
    {
        return view('purchase-orders.form', [
            'order' => new PurchaseOrder(['gst_rate' => config('gst.default_gst_rate', 18), 'issue_date' => now()->toDateString()]),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $r)
    {
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'vendor' => 'required|string|max:255', 'vendor_email' => 'nullable|email',
            'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'expected_on' => 'nullable|date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0', 'assigned_to' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0',
        ]);
        $order = PurchaseOrder::create([
            'number' => $this->nextNumber(), 'vendor' => $data['vendor'], 'vendor_email' => $data['vendor_email'] ?? null,
            'project_id' => $data['project_id'] ?? null, 'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            'expected_on' => $data['expected_on'] ?? null, 'status' => 'draft',
            'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null, 'discount' => $data['discount'] ?? 0,
            'created_by' => auth()->id(), 'assigned_to' => $data['assigned_to'] ?? null,
        ]);
        foreach ($data['items'] as $it) {
            $order->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        $order->recalc();

        return redirect()->route('purchase-orders.show', $order)->with('ok', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['items', 'expense']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->withErrors(['status' => 'Only draft orders can be edited.']);
        }

        return view('purchase-orders.form', [
            'order' => $purchaseOrder->load('items'),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function update(Request $r, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft orders can be edited.']);
        }
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'vendor' => 'required|string|max:255', 'vendor_email' => 'nullable|email',
            'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'expected_on' => 'nullable|date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0', 'assigned_to' => 'nullable|exists:users,id',
            'items' => 'sometimes|array', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0',
            'items_existing' => 'sometimes|array',
            'items_existing.*.description' => 'required|string',
            'items_existing.*.hsn_code' => 'nullable|string|max:20', 'items_existing.*.unit' => 'nullable|string|max:10',
            'items_existing.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items_existing.*.qty' => 'required|numeric|min:0.01', 'items_existing.*.rate' => 'required|numeric|min:0',
            'remove_items' => 'sometimes|array', 'remove_items.*' => 'integer',
        ]);
        $purchaseOrder->update([
            'vendor' => $data['vendor'], 'vendor_email' => $data['vendor_email'] ?? null,
            'project_id' => $data['project_id'] ?? null, 'issue_date' => $data['issue_date'] ?? $purchaseOrder->issue_date,
            'expected_on' => $data['expected_on'] ?? null, 'gst_rate' => $data['gst_rate'],
            'notes' => $data['notes'] ?? null, 'discount' => $data['discount'] ?? 0,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);
        $remove = collect($data['remove_items'] ?? [])->map(fn ($v) => (int) $v);
        foreach ($data['items_existing'] ?? [] as $id => $it) {
            $line = $purchaseOrder->items()->find((int) $id);
            if (! $line) {
                continue;
            }
            if ($remove->contains($line->id)) {
                $line->delete();

                continue;
            }
            $line->update(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        if ($purchaseOrder->items()->whereIn('id', $remove)->exists()) {
            $purchaseOrder->items()->whereIn('id', $remove)->delete();
        }
        if (! empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $purchaseOrder->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'amount' => round($it['qty'] * $it['rate'], 2)]);
            }
        }
        $purchaseOrder->recalc();

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('ok', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();

        return back()->with('ok', 'Purchase order moved to Trash.');
    }

    public function send(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft orders can be sent.']);
        }
        if ($purchaseOrder->items()->count() === 0) {
            return back()->withErrors(['items' => 'Add at least one line first.']);
        }
        $purchaseOrder->update(['status' => 'sent']);

        return back()->with('ok', 'Purchase order sent to '.$purchaseOrder->vendor.'.');
    }

    /** Goods arrived → book the spend. Idempotent via converted_expense_id. */
    public function receive(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'sent') {
            return back()->withErrors(['status' => 'Only sent orders can be received.']);
        }
        if ($purchaseOrder->converted_expense_id && $expense = Expense::find($purchaseOrder->converted_expense_id)) {
            return redirect()->route('expenses.show', $expense)->with('ok', 'Already received.');
        }
        $prefix = setting('expense.prefix', 'EXP-');
        $expense = Expense::create([
            'number' => NextNumber::take($prefix, 'expenses'),
            'title' => 'PO '.$purchaseOrder->number.' — '.$purchaseOrder->vendor,
            'category' => 'purchases', 'project_id' => $purchaseOrder->project_id,
            'amount' => $purchaseOrder->total, 'spent_on' => now()->toDateString(),
            'vendor' => $purchaseOrder->vendor, 'reference' => $purchaseOrder->number,
            'notes' => 'Converted from purchase order '.$purchaseOrder->number.'.', 'created_by' => auth()->id(),
        ]);
        $purchaseOrder->update(['status' => 'received', 'converted_expense_id' => $expense->id]);

        return redirect()->route('expenses.show', $expense)->with('ok', 'Goods received — expense booked.');
    }

    protected function nextNumber(): string
    {
        return NextNumber::take('PO-', 'purchase_orders');
    }
}
