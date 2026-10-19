<?php

namespace Modules\Products\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Products\Product;
use Modules\Products\StockEntry;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }
    public function index(Request $r)
    {
        $q = Product::withSum(['stockEntries as stock_in' => fn ($x) => $x->where('type', 'in')], 'qty')
            ->withSum(['stockEntries as stock_out' => fn ($x) => $x->where('type', 'out')], 'qty')
            ->withSum(['stockEntries as stock_adj' => fn ($x) => $x->where('type', 'adjustment')], 'qty')
            ->orderBy('name');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('sku', 'like', $s)->orWhere('description', 'like', $s)->orWhere('hsn_code', 'like', $s));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        $stats = [
            'total' => Product::count(),
            'active' => Product::where('status', 'active')->count(),
            'avg_rate' => round((float) Product::where('status', 'active')->avg('rate'), 2),
        ];

        return view('products.index', ['products' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'stats' => $stats]);
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['unit' => 'NOS', 'tax_rate' => 18, 'status' => 'active'])]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'sku' => 'nullable|string|max:40',
            'description' => 'nullable|string', 'rate' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:28', 'hsn_code' => 'nullable|string|max:20',
            'unit' => 'nullable|string|max:10', 'status' => 'nullable|in:active,archived',
            'opening_stock' => 'nullable|numeric|min:0', 'low_stock_at' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);
        $data['rate'] ??= 0;
        $data['unit'] ??= 'NOS';
        $data['opening_stock'] ??= 0;
        unset($data['image']);
        if ($r->hasFile('image')) {
            if ($reason = upload_reject_reason($r->file('image'))) {
                return back()->withErrors(['image' => $reason])->withInput();
            }
            $data['image_path'] = $r->file('image')->store('products', 'public');
        }
        Product::create($data);

        return redirect()->route('products.index')->with('ok', 'Product saved.');
    }

    public function edit(Product $product)
    {
        return view('products.form', compact('product'));
    }

    public function update(Request $r, Product $product)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'sku' => 'nullable|string|max:40',
            'description' => 'nullable|string', 'rate' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:28', 'hsn_code' => 'nullable|string|max:20',
            'unit' => 'nullable|string|max:10', 'status' => 'nullable|in:active,archived',
            'opening_stock' => 'nullable|numeric|min:0', 'low_stock_at' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);
        unset($data['image']);
        if ($r->hasFile('image')) {
            if ($reason = upload_reject_reason($r->file('image'))) {
                return back()->withErrors(['image' => $reason])->withInput();
            }
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $r->file('image')->store('products', 'public');
        }
        $product->update($data);

        return redirect()->route('products.index')->with('ok', 'Product updated.');
    }

    /** Stock ledger: every in/out/adjustment with the running on-hand balance. */
    public function stock(Product $product)
    {
        $entries = $product->stockEntries()->orderBy('moved_on')->orderBy('id')->get();
        $bal = (float) ($product->opening_stock ?? 0);
        $rows = [];
        foreach ($entries as $e) {
            $delta = $e->type === 'out' ? -(float) $e->qty : (float) $e->qty;
            $bal = round($bal + $delta, 2);
            $rows[] = ['e' => $e, 'delta' => $delta, 'balance' => $bal];
        }

        return view('products.stock', ['product' => $product, 'rows' => $rows, 'onHand' => $bal]);
    }

    public function stockStore(Request $r, Product $product)
    {
        $data = $r->validate([
            'type' => 'required|in:in,out,adjustment', 'qty' => 'required|numeric',
            'reference' => 'nullable|string|max:255', 'notes' => 'nullable|string',
            'moved_on' => 'required|date',
        ]);
        if ($data['type'] !== 'adjustment' && (float) $data['qty'] <= 0) {
            return back()->withErrors(['qty' => 'In and Out moves need a quantity above zero (adjustments may be negative).'])->withInput();
        }
        $product->stockEntries()->create([
            'type' => $data['type'], 'qty' => $data['qty'], 'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null, 'moved_on' => $data['moved_on'], 'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Stock movement recorded.');
    }

    /** Physical audit: enter the counted qty, the difference posts as an adjustment. */
    public function stockAudit(Request $r, Product $product)
    {
        $data = $r->validate(['counted' => 'required|numeric|min:0', 'moved_on' => 'required|date|before_or_equal:today']);
        $bal = (float) ($product->opening_stock ?? 0);
        foreach ($product->stockEntries()->orderBy('moved_on')->orderBy('id')->get() as $e) {
            $bal = round($bal + ($e->type === 'out' ? -(float) $e->qty : (float) $e->qty), 2);
        }
        $diff = round((float) $data['counted'] - $bal, 2);
        if ($diff == 0) {
            return back()->with('ok', 'Count matches the books ('.$bal.' '.$product->unit.') — nothing to adjust.');
        }
        $product->stockEntries()->create([
            'type' => 'adjustment', 'qty' => $diff, 'reference' => 'Physical audit',
            'notes' => 'Counted '.$data['counted'].', books said '.$bal.'.', 'moved_on' => $data['moved_on'], 'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Audit posted: '.($diff > 0 ? '+' : '').$diff.' '.$product->unit.' (now '.$data['counted'].').');
    }

    public function stockDestroy(Product $product, StockEntry $entry)
    {
        if ($entry->product_id !== $product->id) {
            abort(404);
        }
        $entry->delete();

        return back()->with('ok', 'Stock entry removed.');
    }

    public function destroy(Product $product)
    {
        // Lines on issued documents are snapshots — nothing references back,
        // so archiving to Trash is always safe.
        $product->delete();

        return back()->with('ok', 'Product moved to Trash.');
    }

    /** Bulk delete / status-change from the list checkboxes. */
    public function bulk(Request $r)
    {
        $data = $r->validate([
            'action' => 'required|in:delete,status', 'ids' => 'required|array|max:200', 'ids.*' => 'integer',
            'status' => 'required_if:action,status|in:active,archived',
        ]);
        $done = 0;
        $errors = [];
        foreach (Product::whereIn('id', $data['ids'])->get() as $product) {
            try {
                if ($data['action'] === 'delete') {
                    $product->delete();
                } else {
                    $product->update(['status' => $data['status']]);
                }
                $done++;
            } catch (\Throwable $e) {
                $errors[] = $product->name.': failed';
            }
        }

        return response()->json(['ok' => true, 'done' => $done, 'errors' => array_slice($errors, 0, 5)]);
    }
}
